<?php
declare(strict_types=1);
require __DIR__ . '/../includes/customer_auth.php';
if (customer_user()) { header('Location: ../index.php'); exit; }
require __DIR__ . '/../includes/db.php';
if (empty($_SESSION['customer_csrf'])) $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
$error = '';
$buyNow = (string)($_GET['buy'] ?? $_POST['buy'] ?? '');
if (!preg_match('/^[a-z0-9-]{1,80}$/', $buyNow)) $buyNow = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');
    if (!hash_equals($_SESSION['customer_csrf'], (string)($_POST['csrf'] ?? ''))) $error = 'Your session expired. Please refresh and try again.';
    elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) $error = 'Please enter your full name.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Please enter a valid email address.';
    elseif (!preg_match('/^[0-9+() .-]{7,30}$/', $phone)) $error = 'Please enter a valid phone number.';
    elseif (mb_strlen($address) < 8 || mb_strlen($address) > 500) $error = 'Please enter your complete delivery address.';
    elseif (strlen($password) < 8) $error = 'Choose a password with at least 8 characters.';
    elseif ($password !== $confirmation) $error = 'The passwords do not match.';
    else {
        try {
            $insert = $pdo->prepare('INSERT INTO customers (full_name,email,phone,address,password_hash) VALUES (?,?,?,?,?)');
            $insert->execute([$fullName,$email,$phone,$address,password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['customer'] = ['id'=>(int)$pdo->lastInsertId(),'full_name'=>$fullName,'email'=>$email,'phone'=>$phone,'address'=>$address,'profile_image'=>null];
            if ($buyNow !== '') $_SESSION['pending_buy_now'] = $buyNow;
            unset($_SESSION['customer_csrf']);
            header('Location: ../index.php#products'); exit;
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000' ? 'An account with that email already exists. Sign in instead.' : 'We could not create your account. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#f2f7f1"><title>Create account | MazSen Munch &amp; Sip</title>
  <link rel="icon" href="../logo.jpg" type="image/jpeg">
  <link rel="stylesheet" href="../assets/css/styles.css"><script src="../assets/js/auth.js" defer></script>
</head>
<body class="auth-page">
  <main class="auth-wrap auth-wrap-wide">
    <a class="auth-back" href="../index.php" aria-label="Back to MazSen menu"><span class="auth-back-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M9 14 4 9l5-5M4 9h10a6 6 0 0 1 0 12h-2" /></svg></span><span>Back to menu</span></a>
    <section class="auth-card auth-card-wide" aria-labelledby="auth-title">
      <a class="auth-brand" href="../index.php"><img class="brand-logo" src="../logo.jpg" alt="MazSen Munch &amp; Sip logo"><span class="brand-name">MAZSEN<small>MUNCH &amp; SIP</small></span></a>
      <div class="auth-intro"><span class="auth-kicker">JOIN MAZSEN</span><h1 id="auth-title">Create account</h1><p>Add your contact and delivery details to get your order started.</p></div>
      <?php if ($error): ?><p class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
      <form method="post" class="auth-form auth-form-grid" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['customer_csrf'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="buy" value="<?= htmlspecialchars($buyNow, ENT_QUOTES, 'UTF-8') ?>">
        <label class="auth-span-two">Full name<input name="full_name" autocomplete="name" maxlength="120" value="<?= htmlspecialchars((string)($_POST['full_name']??''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Your full name" required></label>
        <label>Email address<input type="email" name="email" autocomplete="email" maxlength="190" value="<?= htmlspecialchars((string)($_POST['email']??''), ENT_QUOTES, 'UTF-8') ?>" placeholder="you@example.com" required></label>
        <label>Phone number<input type="tel" name="phone" autocomplete="tel" maxlength="30" value="<?= htmlspecialchars((string)($_POST['phone']??''), ENT_QUOTES, 'UTF-8') ?>" placeholder="09XX XXX XXXX" required></label>
        <label class="auth-span-two">Complete delivery address<textarea name="address" autocomplete="street-address" maxlength="500" rows="3" placeholder="House/building, street, barangay, city" required><?= htmlspecialchars((string)($_POST['address']??''), ENT_QUOTES, 'UTF-8') ?></textarea></label>
        <label>Password<input type="password" name="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" required><small>Use at least 8 characters.</small></label>
        <label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" placeholder="Enter password again" required></label>
        <button class="auth-submit auth-span-two" type="submit" data-loading-text="Creating your account…">Create account and continue</button>
      </form>
      <p class="auth-switch">Already have an account? <a href="login.php?next=products<?= $buyNow !== '' ? '&amp;buy=' . rawurlencode($buyNow) : '' ?>">Sign in</a></p>
    </section>
    <p class="auth-footer">Freshly made, always. <span>•</span> MazSen Munch &amp; Sip</p>
  </main>
</body>
</html>
