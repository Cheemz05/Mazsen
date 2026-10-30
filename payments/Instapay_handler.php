<?php
function processInstaPayPayment($orderId, $amount, $refNumber) {
    if (empty($refNumber)) {
        return [
            'status'  => 'pending_verification',
            'message' => 'Bank Transfer / InstaPay Reference Number is required.'
        ];
    }

    global $pdo;
    $pdo->prepare("UPDATE orders SET status = 'Pending Bank Verification' WHERE id = ?")->execute([$orderId]);

    return [
        'status'  => 'success',
        'message' => "InstaPay transaction recorded (Ref #: $refNumber). Staff will confirm receipt upon bank notification."
    ];
}
?>