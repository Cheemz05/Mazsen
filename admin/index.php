<?php
declare(strict_types=1);
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/db.php';

if (admin_user_id() !== null) { header('Location: dashboard.php'); exit; }
$csrf = $_SESSION['admin_login_csrf'] ??= bin2hex(random_bytes(32));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $find = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? LIMIT 1');
    $find->execute([$username]);
    $admin = $find->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['mazsen_owner'] = true;
        $_SESSION['mazsen_admin_id'] = (int)$admin['id'];
        unset($_SESSION['admin_login_csrf']);
        $pdo->prepare('UPDATE admin_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$admin['id']]);
        header('Location: dashboard.php'); exit;
    }
    $error = 'Username or password is incorrect.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Owner Login | MazSen</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="../assets/css/styles.css"></head>
<body class="min-h-screen antialiased"><main class="mx-auto max-w-md px-5 py-16"><section class="rounded-2xl bg-white p-8 shadow-xl"><a class="brand" href="../index.php"><img class="brand-logo" src="../logo.jpg" alt="MazSen Munch &amp; Sip logo"><span class="brand-name">MAZSEN<small>MUNCH &amp; SIP</small></span></a><p class="eyebrow">OWNER ACCESS</p><h1 class="text-3xl font-bold">Admin sign in</h1><p class="subheading">Sign in to view store analytics.</p>
<?php if ($error !== ''): ?><p class="my-4 rounded-lg bg-orange-50 p-3 text-sm text-orange-800"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" class="mt-6 grid gap-4"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><label class="grid gap-1">Username<input class="rounded-lg border p-3" name="username" autocomplete="username" required></label><label class="grid gap-1">Password<input class="rounded-lg border p-3" type="password" name="password" autocomplete="current-password" required></label><button class="primary-button justify-center" type="submit">Sign in</button></form><p class="mt-5 text-center text-sm">First time setup? <a class="text-button" href="signup.php">Create the owner account</a></p></section></main></body></html>
