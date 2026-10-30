<?php
// process_payment.php - Main Payment Processing Gateway (Munch DB Schema)
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);

$customerName   = filter_var($input['customer_name'] ?? '', FILTER_SANITIZE_STRING);
$phone          = filter_var($input['phone'] ?? '', FILTER_SANITIZE_STRING);
$fulfillment    = filter_var($input['fulfillment'] ?? 'delivery', FILTER_SANITIZE_STRING);
$address        = filter_var($input['address'] ?? '', FILTER_SANITIZE_STRING);
$paymentMethod  = filter_var($input['payment_method'] ?? '', FILTER_SANITIZE_STRING);
$items          = $input['items'] ?? [];
$totalAmount    = floatval($input['total_amount'] ?? 0);
$refNumber      = filter_var($input['reference_number'] ?? '', FILTER_SANITIZE_STRING);
$customerId     = isset($input['customer_id']) ? intval($input['customer_id']) : null;

if (empty($customerName) || empty($phone) || empty($items) || $totalAmount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields and add items to your cart.']);
    exit;
}

// Generate unique order number matching schema (VARCHAR 24)
$orderNumber = 'MS-' . strtoupper(substr(uniqid(), -6));

try {
    $pdo->beginTransaction();

    // 1. Save main order to database using munch_db columns
    $stmt = $pdo->prepare("
        INSERT INTO orders (customer_id, customer_name, customer_phone, delivery_address, order_number, status, total, created_at)
        VALUES (:customer_id, :customer_name, :customer_phone, :delivery_address, :order_number, 'Pending', :total, NOW())
    ");

    $stmt->execute([
        ':customer_id'      => $customerId,
        ':customer_name'    => $customerName,
        ':customer_phone'   => $phone,
        ':delivery_address' => $address,
        ':order_number'     => $orderNumber,
        ':total'            => $totalAmount
    ]);

    // Get the auto-incremented primary key `id` for foreign key linking in order_items
    $orderPrimaryKey = $pdo->lastInsertId();

    // 2. Save ordered items (linking via order_id -> orders.id)
    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity) 
        VALUES (:order_id, :product_id, :product_name, :unit_price, :quantity)
    ");

    foreach ($items as $item) {
        $productId = isset($item['product_id']) ? intval($item['product_id']) : 1; // Default product ID fallback
        $itemName  = $item['name'] ?? 'Product';
        $itemPrice = floatval($item['price'] ?? 0);
        $itemQty   = intval($item['quantity'] ?? 1);

        $itemStmt->execute([
            ':order_id'     => $orderPrimaryKey,
            ':product_id'   => $productId,
            ':product_name' => $itemName,
            ':unit_price'   => $itemPrice,
            ':quantity'     => $itemQty
        ]);
    }

    // 3. Delegate to payment handler module
    $paymentResult = ['status' => 'success', 'message' => 'Order placed successfully.'];

    switch (strtolower($paymentMethod)) {
        case 'gcash':
            require_once 'gcash_handler.php';
            $paymentResult = processGCashPayment($orderPrimaryKey, $totalAmount, $refNumber);
            break;

        case 'maya':
            require_once 'maya_handler.php';
            $paymentResult = processMayaPayment($orderPrimaryKey, $totalAmount, $refNumber);
            break;

        case 'instapay':
            require_once 'instapay_handler.php';
            $paymentResult = processInstaPayPayment($orderPrimaryKey, $totalAmount, $refNumber);
            break;

        case 'cod':
            $pdo->prepare("UPDATE orders SET status = 'Cash on Delivery' WHERE id = ?")->execute([$orderPrimaryKey]);
            $paymentResult = [
                'status'  => 'success',
                'message' => 'Order recorded for Cash on Delivery / Pay upon Pickup.'
            ];
            break;

        default:
            break;
    }

    $pdo->commit();

    echo json_encode([
        'status'         => 'success',
        'order_number'   => $orderNumber,
        'order_id'       => $orderPrimaryKey,
        'payment_status' => $paymentResult['status'],
        'message'        => $paymentResult['message']
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status'  => 'error',
        'message' => 'Failed to process order: ' . $e->getMessage()
    ]);
}
?>