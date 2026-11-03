<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/dispatch.php';

function paymongoWebhookResponse(int $status, string $message = ''): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    paymongoWebhookResponse(405, 'Method not allowed');
}

$webhookSecret = trim((string)(getenv('PAYMONGO_WEBHOOK_SECRET') ?: ''));
$signatureHeader = (string)($_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '');
$rawBody = file_get_contents('php://input');
if ($webhookSecret === '' || $signatureHeader === '' || !is_string($rawBody) || $rawBody === '') {
    paymongoWebhookResponse(400, 'Invalid webhook request');
}

$signatureParts = [];
foreach (explode(',', $signatureHeader) as $part) {
    $pair = explode('=', trim($part), 2);
    if (count($pair) === 2) $signatureParts[$pair[0]] = $pair[1];
}
$timestamp = $signatureParts['t'] ?? '';
if (!ctype_digit($timestamp)) {
    paymongoWebhookResponse(401, 'Invalid webhook signature');
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    paymongoWebhookResponse(400, 'Invalid webhook payload');
}
$event = is_array($payload['data'] ?? null) ? $payload['data'] : [];
$legacyAttributes = is_array($event['attributes'] ?? null) ? $event['attributes'] : [];
$eventType = (string)($legacyAttributes['type'] ?? $event['type'] ?? '');
$liveMode = (bool)($legacyAttributes['livemode'] ?? $event['livemode'] ?? false);
$providedSignature = (string)($signatureParts[$liveMode ? 'li' : 'te'] ?? '');
$expectedSignature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $webhookSecret);
if ($providedSignature === '' || !hash_equals($expectedSignature, $providedSignature)) {
    paymongoWebhookResponse(401, 'Invalid webhook signature');
}

if ($eventType !== 'checkout_session.payment.paid') {
    paymongoWebhookResponse(200, 'Ignored');
}

$checkoutSession = $legacyAttributes['data'] ?? $event['data'] ?? null;
if (!is_array($checkoutSession)) {
    paymongoWebhookResponse(400, 'Missing checkout session');
}
$sessionAttributes = is_array($checkoutSession['attributes'] ?? null) ? $checkoutSession['attributes'] : [];
$orderNumber = strtoupper(trim((string)($sessionAttributes['reference_number'] ?? '')));
$sessionId = (string)($checkoutSession['id'] ?? '');
$payments = is_array($sessionAttributes['payments'] ?? null) ? $sessionAttributes['payments'] : [];
$paidPayment = null;
foreach ($payments as $payment) {
    if (!is_array($payment)) continue;
    $paymentAttributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : $payment;
    if (($paymentAttributes['status'] ?? '') === 'paid') {
        $paidPayment = ['id' => (string)($payment['id'] ?? ''), 'attributes' => $paymentAttributes];
        break;
    }
}

if (!preg_match('/^MZ-[A-F0-9]{6}$/', $orderNumber) || !preg_match('/^cs_[A-Za-z0-9]+$/', $sessionId) || !$paidPayment) {
    paymongoWebhookResponse(422, 'Payment details could not be matched');
}

$paidAttributes = $paidPayment['attributes'];
$paidAmount = filter_var($paidAttributes['amount'] ?? null, FILTER_VALIDATE_INT);
$currency = strtoupper((string)($paidAttributes['currency'] ?? ''));
if ($paidAmount === false || $currency !== 'PHP') {
    paymongoWebhookResponse(422, 'Invalid payment amount');
}

try {
    $pdo->beginTransaction();
    $orderQuery = $pdo->prepare('SELECT id, total, status, payment_status FROM orders WHERE order_number = ? AND payment_provider_session_id = ? LIMIT 1 FOR UPDATE');
    $orderQuery->execute([$orderNumber, $sessionId]);
    $order = $orderQuery->fetch();
    if (!$order || (int)$paidAmount !== (int)round((float)$order['total'] * 100)) {
        $pdo->rollBack();
        error_log('PayMongo webhook order/amount mismatch for ' . $orderNumber . '.');
        paymongoWebhookResponse(422, 'Payment does not match an order');
    }

    $shouldDispatch = $order['status'] === 'Awaiting Payment' && $order['payment_status'] === 'pending';
    if ($shouldDispatch) {
        $update = $pdo->prepare("UPDATE orders SET status = 'Pending', payment_status = 'paid', payment_reference = ? WHERE id = ? AND status = 'Awaiting Payment' AND payment_status = 'pending'");
        $update->execute([$paidPayment['id'] !== '' ? $paidPayment['id'] : null, (int)$order['id']]);
        $shouldDispatch = $update->rowCount() === 1;
    }
    $pdo->commit();

    if ($shouldDispatch) {
        try {
            dispatch_offer_next($pdo, (int)$order['id']);
        } catch (Throwable $error) {
            error_log('Paid order dispatch offer failed for ' . $orderNumber . ': ' . $error->getMessage());
        }
    }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('PayMongo webhook processing failed: ' . $error->getMessage());
    paymongoWebhookResponse(500, 'Webhook processing failed');
}

paymongoWebhookResponse(200, 'OK');
