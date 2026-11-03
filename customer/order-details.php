<?php
declare(strict_types=1);
require __DIR__ . '/../includes/customer_auth.php';
$customer = customer_user();
if (!$customer) { header('Location: login.php?next=products'); exit; }
require __DIR__ . '/../includes/db.php';
$query = $pdo->prepare('SELECT full_name, email, phone, address FROM customers WHERE id = ? LIMIT 1');
$query->execute([$customer['id']]);
$details = $query->fetch();
if (!$details || trim((string)$details['address']) === '') { header('Location: settings.php?section=address'); exit; }
if (empty($_SESSION['checkout_csrf'])) $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
$checkoutError = (string)($_SESSION['checkout_error'] ?? '');
unset($_SESSION['checkout_error']);
function order_details_escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f7f4fa">
  <title>Confirm account details | MazSen Munch &amp; Sip</title>
  <link rel="icon" href="../logo.jpg" type="image/jpeg">
  <link rel="stylesheet" href="../assets/css/styles.css?v=116">
  <link rel="stylesheet" href="../assets/css/logout.css?v=1">
  <style>
    .payment-options { display: flex; flex-direction: column; gap: 8px; margin-top: 6px; }
    .payment-option-label { display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; }
  </style>
</head>
<body class="order-details-page">
  <main class="order-details-wrap">
    <a class="order-details-back" href="../index.php#cart" aria-label="Back to cart"><span class="order-details-back-icon" aria-hidden="true">←</span><span>Back to cart</span></a>
    
    <form id="checkout-form" action="../payments/start.php" method="POST" class="order-details-card" aria-labelledby="order-details-title">
      <span class="order-details-kicker">CHECKOUT · STEP 1 OF 2</span>
      <h1 id="order-details-title">Confirm your account details &amp; Payment</h1>
      <p class="order-details-intro">We’ll use these details for this delivery order. Make sure your contact number and address are correct.</p>
      <?php if ($checkoutError !== ''): ?><p class="auth-error" role="alert"><?= order_details_escape($checkoutError) ?></p><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= order_details_escape($_SESSION['checkout_csrf']) ?>">
      <input type="hidden" id="items-json" name="items_json" value="">
      
      <dl class="order-details-list">
        <div><dt>Full name</dt><dd><?= order_details_escape((string)$details['full_name']) ?></dd></div>
        <div><dt>Email address</dt><dd><?= order_details_escape((string)$details['email']) ?></dd></div>
        <div><dt>Phone number</dt><dd><?= order_details_escape((string)$details['phone']) ?></dd></div>
        <div><dt>Delivery address</dt><dd><?= nl2br(order_details_escape((string)$details['address'])) ?></dd></div>
        
        <div>
          <dt>Payment method</dt>
          <dd>
            <div class="payment-options">
              <label class="payment-option-label">
                <input type="radio" name="payment_method" value="cod" checked>
                <span>Cash on Delivery (COD)</span>
              </label>
              <label class="payment-option-label">
                <input type="radio" name="payment_method" value="qrph">
                <span>QR Ph (scan with GCash or a bank app)</span>
              </label>
              <label class="payment-option-label">
                <input type="radio" name="payment_method" value="gcash">
                <span>GCash</span>
              </label>
              <label class="payment-option-label">
                <input type="radio" name="payment_method" value="paymaya">
                <span>Maya</span>
              </label>
            </div>
            <small style="display:block;margin-top:8px;color:#777;font-size:.8rem;">Online payment opens a secure checkout. GCash and Maya can continue in their apps; QR Ph can be scanned with participating bank or wallet apps.</small>
          </dd>
        </div>
      </dl>

      <div class="order-details-actions">
        <a class="order-details-edit" href="settings.php">Edit account details</a>
        <button type="submit" class="order-details-confirm" style="border: none; cursor: pointer; font-size: inherit;">
          Confirm details &amp; place order <span aria-hidden="true">→</span>
        </button>
      </div>
    </form>
  </main>

  <script>
    const checkoutCustomerId = <?= (int)$customer['id'] ?>;
    const checkoutForm = document.getElementById('checkout-form');
    checkoutForm.addEventListener('submit', event => {
      let savedCart = [];
      try {
        savedCart = JSON.parse(localStorage.getItem(`mazsen-cart-v2:${checkoutCustomerId}`) || '[]');
      } catch {}
      const items = Array.isArray(savedCart)
        ? savedCart.filter(item => item && typeof item.id === 'string' && Number.isInteger(Number(item.quantity)) && Number(item.quantity) > 0 && Number(item.quantity) <= 99)
            .map(item => ({ id: item.id, quantity: Number(item.quantity) }))
        : [];
      if (!items.length) {
        event.preventDefault();
        window.alert('Your cart is empty. Return to the menu and add an item first.');
        return;
      }
      document.getElementById('items-json').value = JSON.stringify(items);
      const submitButton = checkoutForm.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Starting checkout…';
      }
    });
  </script>
</body>
</html>
