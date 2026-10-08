<?php
declare(strict_types=1);
require __DIR__ . '/../includes/admin_auth.php';
unset($_SESSION['mazsen_owner'], $_SESSION['mazsen_admin_id'], $_SESSION['admin_login_csrf']);
session_regenerate_id(true);
header('Location: index.php');
exit;
