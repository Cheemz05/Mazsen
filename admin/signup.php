<?php
declare(strict_types=1);
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/db.php';

if (admin_user_id() !== null) { header('Location: dashboard.php'); exit; }
$countAdmins = static function () use ($pdo): int {
    return (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
};
$setupComplete = $countAdmins() > 0;
$csrf = $_SESSION['admin_signup_csrf'] ??= bin2hex(random_bytes(32));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$setupComplete) {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirmation'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
            $error = 'Use 3–80 letters, numbers, dots, dashes, or underscores for the username.';
        } elseif (strlen($password) < 12) {
            $error = 'Choose a password with at least 12 characters.';
        } elseif ($password !== $confirmation) {
            $error = 'The passwords do not match.';
        } else {
            try {
                // Serialize first-owner setup so two simultaneous signups cannot both claim the first account.
                $lock = $pdo->query("SELECT GET_LOCK('mazsen_initial_admin_signup', 5)")->fetchColumn();
                if ((int)$lock !== 1) {
                    $error = 'Setup is busy. Please try again in a moment.';
                } elseif ($countAdmins() > 0) {
                    $setupComplete = true;
                } else {
                    $insert = $pdo->prepare('INSERT INTO admin_users (username,password_hash) VALUES (?,?)');
                    $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                    session_regenerate_id(true);
                    $_SESSION['mazsen_owner'] = true;
                    $_SESSION['mazsen_admin_id'] = (int)$pdo->lastInsertId();
                    unset($_SESSION['admin_signup_csrf']);
                    header('Location: dashboard.php');
                    exit;
                }
            } catch (PDOException $exception) {
                $error = $exception->getCode() === '23000' ? 'That admin username is already in use.' : 'We could not create the admin account. Please try again.';
            } finally {
                if (isset($lock) && (int)$lock === 1) $pdo->query("SELECT RELEASE_LOCK('mazsen_initial_admin_signup')");
            }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Owner Setup | MazSen</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="../assets/css/styles.css"></head>
<body class="min-h-screen antialiased"><main class="mx-auto max-w-md px-5 py-12"><section class="rounded-2xl bg-white p-8 shadow-xl"><a class="brand" href="../index.php"><img class="brand-logo" src="../logo.jpg" alt="MazSen Munch &amp; Sip logo"><span class="brand-name">MAZSEN<small>MUNCH &amp; SIP</small></span></a><p class="eyebrow">OWNER ACCESS</p>
<?php if ($setupComplete): ?><h1 class="text-3xl font-bold">Owner account already set up</h1><p class="subheading">Admin signup is closed because an owner account already exists.</p><a class="primary-button mt-6 justify-center" href="index.php">Go to admin sign in</a>
<?php else: ?><h1 class="text-3xl font-bold">Create the owner account</h1><p class="subheading">This one-time setup creates the first admin account for your store.</p><?php if ($error !== ''): ?><p class="my-4 rounded-lg bg-orange-50 p-3 text-sm text-orange-800"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><form method="post" class="mt-6 grid gap-4"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><label class="grid gap-1">Admin username<input class="rounded-lg border p-3" name="username" autocomplete="username" minlength="3" maxlength="80" pattern="[A-Za-z0-9_.-]+" required></label><label class="grid gap-1">Password<input class="rounded-lg border p-3" type="password" name="password" autocomplete="new-password" minlength="12" required><small class="text-gray-500">Use at least 12 characters.</small></label><label class="grid gap-1">Confirm password<input class="rounded-lg border p-3" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label><button class="primary-button justify-center" type="submit">Create owner account</button></form><?php endif; ?></section></main></body></html>
