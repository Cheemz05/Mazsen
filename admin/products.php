<?php
declare(strict_types=1);
require __DIR__ . '/../includes/admin_auth.php';
if (admin_user_id() === null) {
    header('Location: index.php');
    exit;
}
header('Location: dashboard.php');
exit;
