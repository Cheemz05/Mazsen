<?php
declare(strict_types=1);
require __DIR__ . '/../includes/customer_auth.php';
if (customer_user()) { header('Location: ../index.php#products'); exit; }
require __DIR__ . '/../includes/db.php';
if (empty($_SESSION['customer_csrf'])) $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
$error = '';
$socialError = (string)($_SESSION['customer_social_error'] ?? '');
unset($_SESSION['customer_social_error']);
$next = ($_GET['next'] ?? $_POST['next'] ?? '') === 'products' ? 'products' : 'dashboard';
$buyNow = (string)($_GET['buy'] ?? $_POST['buy'] ?? '');
if (!preg_match('/^[a-z0-9-]{1,80}$/', $buyNow)) $buyNow = '';
$buyQuantity = filter_var($_GET['qty'] ?? $_POST['qty'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>99]]) ?: 1;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['customer_csrf'], (string)($_POST['csrf'] ?? ''))) $error = 'Your session expired. Please refresh and try again.';
    else {
        $query = $pdo->prepare('SELECT id,full_name,email,phone,address,profile_image,password_hash FROM customers WHERE email=? LIMIT 1');
        $query->execute([strtolower(trim((string)($_POST['email'] ?? '')))]);
        $row = $query->fetch();
        if ($row && password_verify((string)($_POST['password'] ?? ''), $row['password_hash'])) {
            session_regenerate_id(true);
            unset($row['password_hash']);
            $_SESSION['customer'] = $row;
            if ($buyNow !== '') $_SESSION['pending_buy_now'] = ['id'=>$buyNow,'quantity'=>$buyQuantity];
            unset($_SESSION['customer_csrf']);
            header('Location: ../index.php#' . $next); exit;
        }
        $error = 'Email or password is incorrect.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#f7f4fa"><title>Customer sign in | MazSen Munch &amp; Sip</title>
  <link rel="icon" href="../logo.jpg" type="image/jpeg">
  <link rel="stylesheet" href="../assets/css/styles.css?v=110"><script src="../assets/js/auth.js" defer></script>
</head>
<body class="auth-page login-page">
  <main class="auth-wrap">
    <a class="auth-back" href="../index.php" aria-label="Back to MazSen menu"><span class="auth-back-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M9 14 4 9l5-5M4 9h10a6 6 0 0 1 0 12h-2" /></svg></span><span>Back to menu</span></a>
    <section class="auth-card" aria-labelledby="auth-title">
      <a class="auth-brand" href="../index.php"><img class="brand-logo" src="../logo.jpg" alt="MazSen Munch &amp; Sip logo"><span class="brand-name">MAZSEN<small>MUNCH &amp; SIP</small></span></a>
      <div class="auth-intro"><span class="auth-kicker">WELCOME BACK</span><h1 id="auth-title">Sign in</h1><p>Log in to order your favorites and keep track of your deliveries.</p></div>
      <?php if ($error): ?><p class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
      <?php if ($socialError): ?><p class="auth-error" role="alert"><?= htmlspecialchars($socialError, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['customer_csrf'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="buy" value="<?= htmlspecialchars($buyNow, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="qty" value="<?= (int)$buyQuantity ?>">
        <label>Email address<input type="email" name="email" autocomplete="email" placeholder="you@example.com" required></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required></label>
        <button class="auth-submit" type="submit" data-loading-text="Signing you in…">Log in</button>
      </form>
      <div class="social-auth-divider"><span>or continue with</span></div>
      <div class="social-auth-actions">
        <a class="social-auth-button social-google" href="google-auth.php?next=<?= rawurlencode($next) ?><?= $buyNow !== '' ? '&amp;buy=' . rawurlencode($buyNow) . '&amp;qty=' . (int)$buyQuantity : '' ?>"><span class="social-google-mark" aria-hidden="true">G</span>Continue with Google</a>
        <a class="social-auth-button social-facebook" href="facebook-auth.php?next=<?= rawurlencode($next) ?><?= $buyNow !== '' ? '&amp;buy=' . rawurlencode($buyNow) . '&amp;qty=' . (int)$buyQuantity : '' ?>"><span class="social-facebook-mark" aria-hidden="true">f</span>Continue with Facebook</a>
      </div>
      <p class="auth-switch">Haven't an account? <a href="signup.php<?= $buyNow !== '' ? '?next=products&amp;buy=' . rawurlencode($buyNow) . '&amp;qty=' . (int)$buyQuantity : '' ?>">Sign up</a></p>
    </section>
    <p class="auth-footer">Freshly made, always. <span>•</span> MazSen Munch &amp; Sip</p>
  </main>
</body>
</html>
