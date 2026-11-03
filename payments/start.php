<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/dispatch.php';

function checkoutRedirectWithError(string $message): never
{
    $_SESSION['checkout_error'] = $message;
    header('Location: ../customer/order-details.php', true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ../customer/order-details.php', true, 303);
    exit;
}

$customer = customer_user();
if (!$customer) {
    header('Location: ../customer/login.php?next=products', true, 303);
    exit;
}

if (empty($_SESSION['checkout_csrf']) || !hash_equals($_SESSION['checkout_csrf'], (string)($_POST['csrf'] ?? ''))) {
    checkoutRedirectWithError('Your checkout session expired. Refresh this page and try again.');
}
$_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));

$selectedMethod = (string)($_POST['payment_method'] ?? '');
$paymentMethods = [
    'cod' => ['label' => 'COD', 'provider' => null],
    'gcash' => ['label' => 'GCash', 'provider' => 'gcash'],
    'paymaya' => ['label' => 'Maya', 'provider' => 'paymaya'],
    'qrph' => ['label' => 'QR Ph', 'provider' => 'qrph'],
];
if (!isset($paymentMethods[$selectedMethod])) {
    checkoutRedirectWithError('Select a valid payment method.');
}

$paymentConfig = $paymentMethods[$selectedMethod];
$secretKey = trim((string)(getenv('PAYMONGO_SECRET_KEY') ?: ''));
$baseUrl = rtrim(trim((string)(getenv('APP_BASE_URL') ?: '')), '/');
if ($paymentConfig['provider'] !== null) {
    $baseParts = parse_url($baseUrl);
    if ($secretKey === '' || !$baseParts || ($baseParts['scheme'] ?? '') !== 'https' || empty($baseParts['host'])) {
        checkoutRedirectWithError('Online payment is not configured yet. Please choose Cash on Delivery or contact the store.');
    }
}

$itemsJson = (string)($_POST['items_json'] ?? '');
$requestedItems = json_decode($itemsJson, true);
if (!is_array($requestedItems) || !$requestedItems || count($requestedItems) > 50) {
    checkoutRedirectWithError('Your cart is empty or invalid. Return to the menu and try again.');
}

$customerQuery = $pdo->prepare('SELECT id, full_name, email, phone, address FROM customers WHERE id = ? LIMIT 1');
$customerQuery->execute([(int)$customer['id']]);
$customerDetails = $customerQuery->fetch();
if (!$customerDetails || strlen(trim((string)$customerDetails['address'])) < 8) {
    header('Location: ../customer/settings.php?section=address', true, 303);
    exit;
}

if (!(bool)$pdo->query('SELECT is_open FROM store_settings WHERE id = 1')->fetchColumn()) {
    checkoutRedirectWithError('The store is closed right now and cannot accept new orders.');
}

$productQuery = $pdo->prepare('SELECT id, slug, name, price FROM products WHERE slug = ? AND active = 1 LIMIT 1');
$items = [];
$totalCents = 0;
foreach ($requestedItems as $requestedItem) {
    if (!is_array($requestedItem)) {
        checkoutRedirectWithError('Your cart contains an invalid item. Return to the menu and try again.');
    }
    $slug = (string)($requestedItem['id'] ?? '');
    $quantity = filter_var($requestedItem['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
    $productQuery->execute([$slug]);
    $product = $productQuery->fetch();
    if (!$product || $quantity === false) {
        checkoutRedirectWithError('An item in your cart is unavailable. Refresh the menu and try again.');
    }
    $unitCents = (int)round((float)$product['price'] * 100);
    $items[] = ['product' => $product, 'quantity' => $quantity, 'unit_cents' => $unitCents];
    $totalCents += $unitCents * $quantity;
}

if ($totalCents < 100 || $totalCents > 999999999) {
    checkoutRedirectWithError('This order total is outside the supported online payment amount.');
}

$orderNumber = 'MZ-' . strtoupper(bin2hex(random_bytes(3)));
$onlinePayment = $paymentConfig['provider'] !== null;
$orderStatus = $onlinePayment ? 'Awaiting Payment' : 'Pending';
$paymentStatus = $onlinePayment ? 'pending' : 'unpaid';
$orderId = 0;

try {
    $pdo->beginTransaction();
    $insertOrder = $pdo->prepare('INSERT INTO orders (customer_id, customer_name, customer_phone, delivery_address, order_number, status, payment_method, payment_status, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insertOrder->execute([
        (int)$customerDetails['id'],
        (string)$customerDetails['full_name'],
        (string)$customerDetails['phone'],
        (string)$customerDetails['address'],
        $orderNumber,
        $orderStatus,
        $paymentConfig['label'],
        $paymentStatus,
        $totalCents / 100,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $insertLine = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity) VALUES (?, ?, ?, ?, ?)');
    foreach ($items as $item) {
        $product = $item['product'];
        $insertLine->execute([$orderId, (int)$product['id'], (string)$product['name'], $product['price'], $item['quantity']]);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('MazSen checkout order creation failed: ' . $error->getMessage());
    checkoutRedirectWithError('We could not place your order. Please try again.');
}

if (!$onlinePayment) {
    try {
        dispatch_offer_next($pdo, $orderId);
    } catch (Throwable $error) {
        error_log('MazSen COD dispatch offer failed for ' . $orderNumber . ': ' . $error->getMessage());
    }
    header('Location: ../index.php?view=orders&order_placed=1', true, 303);
    exit;
}

$lineItems = array_map(static function (array $item): array {
    return [
        'name' => (string)$item['product']['name'],
        'amount' => (int)$item['unit_cents'],
        'currency' => 'PHP',
        'quantity' => (int)$item['quantity'],
    ];
}, $items);
$returnUrl = $baseUrl . '/customer/payment-return.php?order=' . rawurlencode($orderNumber);
$requestBody = json_encode([
    'data' => [
        'attributes' => [
            'line_items' => $lineItems,
            'payment_method_types' => [$paymentConfig['provider']],
            'success_url' => $returnUrl . '&result=success',
            'cancel_url' => $returnUrl . '&result=cancel',
            'reference_number' => $orderNumber,
            'metadata' => [
                'order_id' => (string)$orderId,
                'order_number' => $orderNumber,
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Authorization: Basic " . base64_encode($secretKey . ':') . "\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
        'content' => $requestBody,
        'timeout' => 20,
        'ignore_errors' => true,
    ],
]);
$responseBody = @file_get_contents('https://api.paymongo.com/v2/checkout_sessions', false, $context);
$response = is_string($responseBody) ? json_decode($responseBody, true) : null;
$checkoutUrl = $response['data']['attributes']['checkout_url'] ?? '';
$checkoutSessionId = $response['data']['id'] ?? '';
$checkoutParts = is_string($checkoutUrl) ? parse_url($checkoutUrl) : false;
$statusLine = $http_response_header[0] ?? '';
$successfulResponse = preg_match('/\s2\d\d\s/', $statusLine) === 1;

if (!$successfulResponse || !$checkoutParts || ($checkoutParts['scheme'] ?? '') !== 'https' || ($checkoutParts['host'] ?? '') !== 'checkout.paymongo.com' || !preg_match('/^cs_[A-Za-z0-9]+$/', (string)$checkoutSessionId)) {
    $pdo->prepare("UPDATE orders SET status = 'Payment Failed', payment_status = 'failed' WHERE id = ? AND payment_status = 'pending'")->execute([$orderId]);
    error_log('PayMongo checkout session creation failed for ' . $orderNumber . ' (' . $statusLine . ').');
    checkoutRedirectWithError('We could not start the secure payment page. No payment was taken. Please try again or choose Cash on Delivery.');
}

$saveSession = $pdo->prepare('UPDATE orders SET payment_provider_session_id = ?, payment_checkout_url = ? WHERE id = ? AND payment_status = ?');
$saveSession->execute([(string)$checkoutSessionId, (string)$checkoutUrl, $orderId, 'pending']);
if ($saveSession->rowCount() !== 1) {
    error_log('PayMongo session could not be linked to order ' . $orderNumber . '.');
    checkoutRedirectWithError('We could not finish setting up payment. Please contact the store before trying again.');
}

header('Location: ' . $checkoutUrl, true, 303);
exit;
