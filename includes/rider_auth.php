<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

function rider_user_id(): ?int
{
    return isset($_SESSION['mazsen_rider_id']) ? (int)$_SESSION['mazsen_rider_id'] : null;
}
