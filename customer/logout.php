<?php
declare(strict_types=1);
require __DIR__ . '/../includes/customer_auth.php';
unset($_SESSION['customer'], $_SESSION['customer_csrf']);
session_regenerate_id(true);
header('Location: ../index.php');
exit;
