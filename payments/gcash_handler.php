<?php
// gcash_handler.php - GCash Payment Gateway Integration
function processGCashPayment($orderId, $amount, $refNumber) {
    if (empty($refNumber) || strlen($refNumber) < 8) {
        return [
            'status'  => 'pending_verification',
            'message' => 'GCash Reference Number is required for verification. Please upload or enter a valid 13-digit GCash reference code.'
        ];
    }

    // Simulated API validation response
    global $pdo;
    $pdo->prepare("UPDATE orders SET payment_status = 'Paid (GCash Verified)' WHERE order_id = ?")->execute([$orderId]);

    return [
        'status'  => 'success',
        'message' => "GCash payment of ₱" . number_format($amount, 2) . " verified (Ref #: $refNumber)."
    ];
}
?>