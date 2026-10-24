<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}
function customer_user(): ?array
{
    return isset($_SESSION['customer']) && is_array($_SESSION['customer']) ? $_SESSION['customer'] : null;
}
