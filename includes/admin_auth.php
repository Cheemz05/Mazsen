<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

function admin_user_id(): ?int
{
    return !empty($_SESSION['mazsen_owner']) && isset($_SESSION['mazsen_admin_id'])
        ? (int)$_SESSION['mazsen_admin_id']
        : null;
}
