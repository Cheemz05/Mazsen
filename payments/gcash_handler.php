<?php
function processGCashPayment($orderId, $amount, $refNumber) {
    if (empty($refNumber) || strlen($refNumber) < 8) {
        return [
            'status'  => 'pending_verification',
            'message' => 'GCash Reference Number is required for verification.'
        ];
    }

    global $pdo;
    $pdo->prepare("UPDATE orders SET status = 'Paid (GCash Verified)' WHERE id = ?")->execute([$orderId]);

    return [
        'status'  => 'success',
        'message' => "GCash payment of ₱" . number_format($amount, 2) . " verified (Ref #: $refNumber)."
    ];
}
?>