<?php
declare(strict_types=1);

// XAMPP defaults: user "root" with no password. Update for your local setup.
$host = '127.0.0.1';
$database = 'munch_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $exception) {
    http_response_code(503);
    if (str_ends_with(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api.php')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Database connection failed. Import database/schema.sql and check includes/db.php settings.']);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Database unavailable</title><body><h1>Database unavailable</h1><p>Import database/schema.sql in phpMyAdmin and check the connection settings in includes/db.php.</p></body></html>';
    }
    exit;
}
