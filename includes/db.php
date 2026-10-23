<?php
$host = 'mysql-769509f-pingdspornillos-35bb.k.aivencloud.com'; // From your Aiven overview
$port = '24997'; // From your Aiven overview
$dbname = 'defaultdb'; // Your Aiven database name[cite: 7]
$username = 'avnadmin'; // Your Aiven username[cite: 7]
$password = 'AVNS_wXD2LTzidcGTHphuacm'; // Replace with your actual Aiven password[cite: 7]

// Path to the CA certificate you downloaded from Aiven
$ca_cert = __DIR__ . '/ca.pem'; // Place your downloaded ca.pem file in the same folder

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
