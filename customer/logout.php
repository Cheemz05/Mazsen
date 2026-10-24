<?php
declare(strict_types=1);
require __DIR__ . '/../includes/customer_auth.php';
unset($_SESSION['customer'], $_SESSION['customer_csrf']);
session_regenerate_id(true);
<<<<<<< HEAD
header('Location: welcome.php');
=======
header('Location: ../index.php');
>>>>>>> 69464754c591a639bef769c3d69740596b4fd76c
exit;
