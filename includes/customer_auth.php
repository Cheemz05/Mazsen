<?php
declare(strict_types=1);
require_once __DIR__ . '/session.php';

function customer_user(): ?array
{
    return isset($_SESSION['customer']) && is_array($_SESSION['customer']) ? $_SESSION['customer'] : null;
}
