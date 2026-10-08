<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function admin_user_id(): ?int
{
    return !empty($_SESSION['mazsen_owner']) && isset($_SESSION['mazsen_admin_id'])
        ? (int)$_SESSION['mazsen_admin_id']
        : null;
}
