<?php
declare(strict_types=1);
require __DIR__ . '/../includes/customer_auth.php';
if (customer_user()) { header('Location: ../index.php'); exit; }
$buyNow = (string)($_GET['buy'] ?? '');
if (!preg_match('/^[a-z0-9-]{1,80}$/', $buyNow)) $buyNow = '';
$buyQuantity = filter_var($_GET['qty'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>99]]) ?: 1;
$buyQuery = $buyNow !== '' ? '?buy=' . rawurlencode($buyNow) . '&qty=' . (int)$buyQuantity : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#f7f4fa"><title>Get started | MazSen Munch &amp; Sip</title>
  <link rel="icon" href="../logo.jpg" type="image/jpeg"><link rel="stylesheet" href="../assets/css/styles.css?v=118"><script src="../assets/js/auth.js" defer></script>
</head>
<body class="auth-page welcome-page">
  <main class="welcome-wrap">
    <section class="welcome-card" aria-labelledby="welcome-title">
      <nav class="welcome-nav" aria-label="Main navigation"><a href="../index.php">Home</a><a href="../index.php#about">About us</a><a href="../index.php#menu">Menu</a><a href="../index.php#contact">Contact</a></nav>
      <a class="auth-brand welcome-brand" href="../index.php"><img class="brand-logo" src="../logo.jpg" alt=""><span class="brand-name">MAZSEN<small>MUNCH &amp; SIP</small></span></a>
      <div class="welcome-copy"><span class="welcome-eyebrow"><i aria-hidden="true"></i> WELCOME TO MAZSEN</span><h1 id="welcome-title">Good food starts here.</h1><p>Join us for freshly made favorites, snacks, and sips. Create an account or sign in to get started.</p></div>
      <div class="welcome-actions"><a class="welcome-primary" href="signup.php<?= htmlspecialchars($buyQuery, ENT_QUOTES, 'UTF-8') ?>"><span>Create an account</span></a><a class="welcome-secondary" href="login.php<?= $buyNow !== '' ? '?buy=' . rawurlencode($buyNow) . '&amp;qty=' . (int)$buyQuantity : '' ?>"><span>Sign in to your account</span></a></div>
      <div class="welcome-bottom"><span>Freshly made, always.</span><a class="welcome-menu-link" href="../index.php">Browse the menu</a></div>
      <img class="welcome-food" src="../Photos/fitted-4x3-fill/Classic%20Burger.png" alt="MazSen classic burger">
    </section>
  </main>
</body>
</html>
