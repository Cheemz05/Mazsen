<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php'; 

if (isset($pdo) && session_status() !== PHP_SESSION_ACTIVE) {
    session_set_save_handler(
        function($path, $name) { return true; },
        function() { return true; },
        function($id) use ($pdo) {
            $stmt = $pdo->prepare("SELECT data FROM php_sessions WHERE session_id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ? (string)$row['data'] : '';
        },
        function($id, $data) use ($pdo) {
            $stmt = $pdo->prepare("REPLACE INTO php_sessions (session_id, access, data) VALUES (?, UNIX_TIMESTAMP(), ?)");
            return $stmt->execute([$id, $data]);
        },
        function($id) use ($pdo) {
            $stmt = $pdo->prepare("DELETE FROM php_sessions WHERE session_id = ?");
            return $stmt->execute([$id]);
        },
        function($maxlifetime) use ($pdo) {
            $old = time() - $maxlifetime;
            $stmt = $pdo->prepare("DELETE FROM php_sessions WHERE access < ?");
            return $stmt->execute([$old]);
        }
    );
}

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
?>