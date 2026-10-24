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
</head>
<body class="order-details-page">
  <main class="order-details-wrap">
    <a class="order-details-back" href="../index.php#cart" aria-label="Back to cart"><span class="order-details-back-icon" aria-hidden="true">←</span><span>Back to cart</span></a>
    <section class="order-details-card" aria-labelledby="order-details-title">
      <span class="order-details-kicker">CHECKOUT · STEP 1 OF 2</span>
      <h1 id="order-details-title">Confirm your account details</h1>
      <p class="order-details-intro">We’ll use these details for this delivery order. Make sure your contact number and address are correct.</p>
      <dl class="order-details-list">
        <div><dt>Full name</dt><dd><?= order_details_escape((string)$details['full_name']) ?></dd></div>
        <div><dt>Email address</dt><dd><?= order_details_escape((string)$details['email']) ?></dd></div>
        <div><dt>Phone number</dt><dd><?= order_details_escape((string)$details['phone']) ?></dd></div>
        <div><dt>Delivery address</dt><dd><?= nl2br(order_details_escape((string)$details['address'])) ?></dd></div>
        <div><dt>Payment</dt><dd>Cash on delivery</dd></div>
      </dl>
      <div class="order-details-actions">
        <a class="order-details-edit" href="settings.php">Edit account details</a>
        <a class="order-details-confirm" href="../index.php?checkout_confirmed=1">Confirm details &amp; place order <span aria-hidden="true">→</span></a>
      </div>
    </section>
  </main>
</body>
</html>
