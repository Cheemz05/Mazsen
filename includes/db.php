<?php
$host = 'mysql-769509f-pingdspornillos-35bb.k.aivencloud.com'; 
$port = '24997'; 
$dbname = 'defaultdb';
$username = 'avnadmin';
$password = 'AVNS_N0L3ircRLMuLTTFXcyM';

$ca_cert = __DIR__ . '/ca.pem';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Enforce SSL connection required by Aiven
        PDO::MYSQL_ATTR_SSL_CA => $ca_cert,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);
    // Connection successful!
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
