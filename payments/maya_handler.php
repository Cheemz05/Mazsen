<?php
function processMayaPayment($orderId, $amount, $refNumber) {
    if (empty($refNumber) || strlen($refNumber) < 6) {
        return [
            'status'  => 'pending_verification',
            'message' => 'Maya Reference Code is missing or invalid.'
        ];
    }

    global $pdo;
    $pdo->prepare("UPDATE orders SET status = 'Paid (Maya Verified)' WHERE id = ?")->execute([$orderId]);

    return [
        'status'  => 'success',
        'message' => "Maya payment of ₱" . number_format($amount, 2) . " successfully verified (Ref #: $refNumber)."
    ];
}
?>