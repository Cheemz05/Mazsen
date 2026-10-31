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
    #reference-box { margin-top: 10px; display: none; }
    #reference-box input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; margin-top: 4px; }
  </style>
</head>
<body class="order-details-page">
  <main class="order-details-wrap">
    <a class="order-details-back" href="../index.php#cart" aria-label="Back to cart"><span class="order-details-back-icon" aria-hidden="true">←</span><span>Back to cart</span></a>
    
    <form action="../index.php?checkout_confirmed=1" method="POST" class="order-details-card" aria-labelledby="order-details-title">
      <span class="order-details-kicker">CHECKOUT · STEP 1 OF 2</span>
      <h1 id="order-details-title">Confirm your account details &amp; Payment</h1>
      <p class="order-details-intro">We’ll use these details for this delivery order. Make sure your contact number and address are correct.</p>
      
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
                <input type="radio" name="payment_method" value="COD" checked onchange="togglePaymentRef(false)">
                <span>Cash on Delivery (COD)</span>
              </label>
              <label class="payment-option-label">
                <input type="radio" name="payment_method" value="InstaPay" onchange="togglePaymentRef(true, 'InstaPay')">
                <span>InstaPay / Bank Transfer</span>
              </label>
              <label class="payment-option-label">
                <input type="radio" name="payment_method" value="GCash" onchange="togglePaymentRef(true, 'GCash')">
                <span>GCash</span>
              </label>
              <label class="payment-option-label">
                <input type="radio" name="payment_method" value="Maya" onchange="togglePaymentRef(true, 'Maya')">
                <span>Maya</span>
              </label>
            </div>

            <div id="reference-box">
              <label id="ref-label" style="font-size: 0.85rem; font-weight: 600; color: #555;">Reference Number</label>
              <input type="text" name="payment_reference" placeholder="Enter transaction ref number">
              <small style="color: #777; font-size: 0.75rem;">Please transfer payment to our official account before placing the order.</small>
            </div>
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
    function togglePaymentRef(show, methodName = '') {
      const refBox = document.getElementById('reference-box');
      const refLabel = document.getElementById('ref-label');
      if (show) {
        refBox.style.display = 'block';
        refLabel.innerText = methodName + ' Reference Number';
      } else {
        refBox.style.display = 'none';
      }
    }
  </script>
</body>
</html>