<?php
declare(strict_types=1);

require __DIR__ . '/../includes/customer_auth.php';
$customer = customer_user();
if (!$customer) {
    header('Location: login.php?next=orders', true, 303);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$orderNumber = strtoupper(trim((string)($_GET['order'] ?? '')));
if (!preg_match('/^MZ-[A-F0-9]{6}$/', $orderNumber)) {
    http_response_code(400);
    exit('Invalid order reference.');
}

$query = $pdo->prepare('SELECT order_number, status, payment_method, payment_status, payment_checkout_url, total FROM orders WHERE order_number = ? AND customer_id = ? LIMIT 1');
$query->execute([$orderNumber, (int)$customer['id']]);
$order = $query->fetch();
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$isPaid = $order['payment_status'] === 'paid';
$isPending = $order['payment_status'] === 'pending';
$isCod = $order['payment_method'] === 'COD';
$heading = $isCod ? 'Order placed' : ($isPaid ? 'Payment confirmed' : ($isPending ? 'Checking your payment' : 'Payment not completed'));
$message = $isCod
    ? 'Your order is recorded. Pay the rider when it arrives.'
    : ($isPaid
        ? 'Your payment has been confirmed. Your order is now with the store.'
        : ($isPending
            ? 'The payment provider has returned you to MazSen. We are waiting for its secure confirmation; this page does not mark a payment as paid by itself.'
            : 'No successful payment was recorded and the store has not started preparing this order. Return to the menu to place another order or contact the store.'));
$checkoutUrl = $isPending ? (string)($order['payment_checkout_url'] ?? '') : '';
function payment_return_escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f7f4fa">
  <title><?= payment_return_escape($heading) ?> | MazSen Munch &amp; Sip</title>
  <link rel="icon" href="../logo.jpg" type="image/jpeg">
  <link rel="stylesheet" href="../assets/css/styles.css?v=202">
  <style>
    .payment-return-wrap{min-height:100vh;display:grid;place-items:center;padding:24px;background:#f7f4fa}
    .payment-return-card{width:min(100%,560px);padding:clamp(28px,6vw,52px);border:1px solid #e9deee;border-radius:28px;background:#fff;box-shadow:0 24px 70px #32143b12}
    .payment-return-card h1{margin:10px 0;font-size:clamp(2rem,5vw,3rem);line-height:1.05;color:#21132c}
    .payment-return-card p{color:#6d6474;line-height:1.65}
    .payment-return-order{margin:24px 0;padding:16px;border:1px solid #eee5f1;border-radius:16px;color:#30213a}
    .payment-return-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
  </style>
</head>
<body>
  <main class="payment-return-wrap">
    <section class="payment-return-card" aria-labelledby="payment-title">
      <span class="eyebrow">MAZSEN CHECKOUT</span>
      <h1 id="payment-title"><?= payment_return_escape($heading) ?></h1>
      <p><?= payment_return_escape($message) ?></p>
      <div class="payment-return-order"><strong>Order <?= payment_return_escape((string)$order['order_number']) ?></strong><br><?= payment_return_escape((string)$order['payment_method']) ?> · ₱<?= number_format((float)$order['total'], 2) ?><br>Order status: <?= payment_return_escape((string)$order['status']) ?></div>
      <div class="payment-return-actions">
        <?php if ($checkoutUrl !== ''): ?><a class="primary-button" href="<?= payment_return_escape($checkoutUrl) ?>">Continue secure checkout</a><?php endif; ?>
        <a class="outline-button" href="../index.php?view=orders">View my orders</a>
      </div>
    </section>
  </main>
  <script>localStorage.removeItem(<?= json_encode('mazsen-cart-v2:' . (int)$customer['id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);</script>
</body>
</html>
