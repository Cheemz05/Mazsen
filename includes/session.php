<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPdo = $pdo;
    $lifetime = 7 * 24 * 60 * 60;
    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';

    ini_set('session.gc_maxlifetime', (string)$lifetime);

    session_set_save_handler(
        static fn(string $path, string $name): bool => true,
        static fn(): bool => true,
        static function (string $id) use ($sessionPdo): string {
            $stmt = $sessionPdo->prepare('SELECT data FROM php_sessions WHERE session_id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ? (string)$row['data'] : '';
        },
        static function (string $id, string $data) use ($sessionPdo): bool {
            $stmt = $sessionPdo->prepare('REPLACE INTO php_sessions (session_id, access, data) VALUES (?, UNIX_TIMESTAMP(), ?)');
            return $stmt->execute([$id, $data]);
        },
        static function (string $id) use ($sessionPdo): bool {
            $stmt = $sessionPdo->prepare('DELETE FROM php_sessions WHERE session_id = ?');
            return $stmt->execute([$id]);
        },
        static function (int $maxLifetime) use ($sessionPdo): int {
            $stmt = $sessionPdo->prepare('DELETE FROM php_sessions WHERE access < ?');
            $stmt->execute([time() - $maxLifetime]);
            return $stmt->rowCount();
        }
    );

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
