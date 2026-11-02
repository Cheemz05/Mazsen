<?php
declare(strict_types=1);
require __DIR__ . '/includes/rider_auth.php';
require __DIR__ . '/includes/db.php';

if (isset($_GET['logout'])) {
    unset($_SESSION['mazsen_rider_id'], $_SESSION['mazsen_rider_name'], $_SESSION['rider_location_csrf']);
    session_regenerate_id(true);
    header('Location: rider-login.php');
    exit;
}
if (rider_user_id() !== null) { header('Location: rider-home.php'); exit; }
if (empty($_SESSION['rider_login_csrf'])) $_SESSION['rider_login_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['rider_login_csrf'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $phone = trim((string)($_POST['phone'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $query = $pdo->prepare('SELECT id, full_name, password_hash, is_active FROM delivery_riders WHERE phone = ? LIMIT 1');
        $query->execute([$phone]);
        $rider = $query->fetch();
        if (!$rider || empty($rider['password_hash']) || !password_verify($password, (string)$rider['password_hash'])) {
            $error = 'Phone number or password is incorrect.';
        } elseif (!(bool)$rider['is_active']) {
            $error = 'Your rider account is inactive. Contact the store owner.';
        } else {
            session_regenerate_id(true);
            $_SESSION['mazsen_rider_id'] = (int)$rider['id'];
            $_SESSION['mazsen_rider_name'] = (string)$rider['full_name'];
            $_SESSION['rider_location_csrf'] = bin2hex(random_bytes(32));
            unset($_SESSION['rider_login_csrf']);
            header('Location: rider-home.php');
            exit;
        }
    }
}
function rider_login_h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#f7f4fa">
  <title>Rider sign in | MazSen Munch &amp; Sip</title>
  <link rel="stylesheet" href="assets/css/styles.css?v=116">
  <style>
    body{min-height:100vh;display:grid;place-items:center;padding:24px;background:#f7f4fa;color:#292035;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}.rider-login{width:min(100%,420px);padding:30px;border:1px solid #e8ddec;border-radius:22px;background:#fff;box-shadow:0 18px 50px #30143a12}.rider-login h1{margin:0;font-size:28px}.rider-login p{color:#746779}.rider-login label{display:grid;gap:7px;margin:16px 0;font-size:13px;font-weight:650}.rider-login input{width:100%;padding:12px;border:1px solid #dfd5e4;border-radius:10px;font:inherit}.rider-login button{width:100%;padding:13px;border:0;border-radius:11px;background:#78439a;color:white;font:inherit;font-weight:750;cursor:pointer}.rider-error{padding:11px;border-radius:9px;background:#fff0ed;color:#984f44;font-size:13px}.rider-brand{color:#78439a;font-size:12px;font-weight:850;letter-spacing:.14em}
  </style>
</head>
<body>
  <main class="rider-login">
    <div class="rider-brand">MAZSEN MUNCH &amp; SIP · RIDER</div>
    <h1>Rider sign in</h1>
    <p>Sign in with the phone number and password provided by the store owner.</p>
    <?php if ($error !== ''): ?><div class="rider-error" role="alert"><?= rider_login_h($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= rider_login_h($csrf) ?>">
      <label>Phone number<input name="phone" type="tel" autocomplete="username" required></label>
      <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
      <button type="submit">Sign in</button>
    </form>
  </main>
</body>
</html>
