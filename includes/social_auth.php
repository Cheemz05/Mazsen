<?php
declare(strict_types=1);

require_once __DIR__ . '/customer_auth.php';
require_once __DIR__ . '/db.php';

function social_auth_config(string $provider): array
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUri = $scheme . '://' . $host;

    if ($provider === 'google') {
        $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: '';
        $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET') ?: '';

        if ($clientId === '' || $clientSecret === '') {
            social_auth_fail('Google sign-in is not configured yet. Add the app credentials in the server environment and try again.');
        }

        return [
            'id' => $clientId,
            'secret' => $clientSecret,
            'callback' => $baseUri . '/customer/google-auth.php',
        ];
    }

    if ($provider === 'facebook') {
        $appId = $_ENV['FACEBOOK_APP_ID'] ?? getenv('FACEBOOK_APP_ID') ?: '';
        $appSecret = $_ENV['FACEBOOK_APP_SECRET'] ?? getenv('FACEBOOK_APP_SECRET') ?: '';

        if ($appId === '' || $appSecret === '') {
            social_auth_fail('Facebook sign-in is not configured yet. Add the app credentials in the server environment and try again.');
        }

        return [
            'id' => $appId,
            'secret' => $appSecret,
            'callback' => $baseUri . '/customer/facebook-auth.php',
        ];
    }

    social_auth_fail('Unsupported login provider.');
}

function social_auth_fail(string $message): never
{
    unset($_SESSION['customer_social_oauth']);
    $_SESSION['customer_social_error'] = $message;
    header('Location: login.php');
    exit;
}

function social_auth_http(string $url, ?array $postFields = null, ?string $bearerToken = null): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => array_filter([
            'Accept: application/json',
            'User-Agent: Mazsen-App',
            $bearerToken ? 'Authorization: Bearer ' . $bearerToken : null,
        ]),
    ]);

    if ($postFields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    }

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($errno !== 0 || !is_string($response)) {
        throw new RuntimeException('Connection failed: ' . ($error !== '' ? $error : 'Unknown cURL error'));
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : [];
}

function social_auth_controller(string $provider): never
{
    $config = social_auth_config($provider);

    if (!isset($_GET['code']) && !isset($_GET['error'])) {
        $state = bin2hex(random_bytes(16));

        $next = isset($_GET['next']) && is_string($_GET['next']) ? trim($_GET['next']) : '';
        $buy = isset($_GET['buy']) && is_string($_GET['buy']) ? trim($_GET['buy']) : '';
        $qty = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1;

        $_SESSION['customer_social_oauth'] = [
            'provider' => $provider,
            'state' => $state,
            'next' => $next,
            'buy' => $buy,
            'quantity' => $qty > 0 ? $qty : 1,
        ];

        if ($provider === 'google') {
            $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => $config['id'],
                'redirect_uri' => $config['callback'],
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'prompt' => 'select_account',
            ]);
        } else {
            $url = 'https://www.facebook.com/v20.0/dialog/oauth?' . http_build_query([
                'client_id' => $config['id'],
                'redirect_uri' => $config['callback'],
                'response_type' => 'code',
                'scope' => 'email,public_profile',
                'state' => $state,
            ]);
        }

        header('Location: ' . $url);
        exit;
    }

    $pending = $_SESSION['customer_social_oauth'] ?? null;
    unset($_SESSION['customer_social_oauth']);

    if (isset($_GET['error']) || !is_array($pending) || ($pending['provider'] ?? '') !== $provider || !hash_equals((string)($pending['state'] ?? ''), (string)($_GET['state'] ?? ''))) {
        social_auth_fail('Your session expired. Please refresh and try again.');
    }

    try {
        if ($provider === 'google') {
            $tokens = social_auth_http('https://oauth2.googleapis.com/token', [
                'code' => (string)$_GET['code'],
                'client_id' => $config['id'],
                'client_secret' => $config['secret'],
                'redirect_uri' => $config['callback'],
                'grant_type' => 'authorization_code',
            ]);

            if (empty($tokens['access_token'])) {
                throw new RuntimeException('Could not finish Google sign-in.');
            }

            $profile = social_auth_http('https://openidconnect.googleapis.com/v1/userinfo', null, (string)$tokens['access_token']);
            if (($profile['email_verified'] ?? false) !== true && ($profile['email_verified'] ?? '') !== 'true') {
                throw new RuntimeException('Google must confirm your email address before you can sign in.');
            }

            $providerId = (string)($profile['sub'] ?? '');
        } else {
            $tokens = social_auth_http('https://graph.facebook.com/v20.0/oauth/access_token?' . http_build_query([
                'client_id' => $config['id'],
                'client_secret' => $config['secret'],
                'redirect_uri' => $config['callback'],
                'code' => (string)$_GET['code'],
            ]));

            if (empty($tokens['access_token'])) {
                throw new RuntimeException('Could not finish Facebook sign-in.');
            }

            $profile = social_auth_http('https://graph.facebook.com/v20.0/me?fields=id,name,email', null, (string)$tokens['access_token']);
            $providerId = (string)($profile['id'] ?? '');

            if (empty($profile['email'])) {
                throw new RuntimeException('Facebook did not share an email address. Allow email access or use email sign-up.');
            }
        }

        $email = strtolower(trim((string)($profile['email'] ?? '')));
        $name = trim((string)($profile['name'] ?? ''));

        if ($providerId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
            throw new RuntimeException('The provider did not return the account details needed to log in.');
        }

        $pdo = getDB();
        $column = $provider === 'google' ? 'google_sub' : 'facebook_id';

        $find = $pdo->prepare("SELECT id, full_name, email, phone, address, profile_image FROM customers WHERE {$column} = ? LIMIT 1");
        $find->execute([$providerId]);
        $customer = $find->fetch();

        if (!$customer) {
            $find = $pdo->prepare('SELECT id, full_name, email, phone, address, profile_image FROM customers WHERE email = ? LIMIT 1');
            $find->execute([$email]);
            $customer = $find->fetch();

            if ($customer) {
                $link = $pdo->prepare("UPDATE customers SET {$column} = ? WHERE id = ? AND ({$column} IS NULL OR {$column} = '')");
                $link->execute([$providerId, $customer['id']]);
                if ($link->rowCount() === 0) {
                    throw new RuntimeException('This email is already linked to a different social account.');
                }
            } else {
                $insert = $pdo->prepare("INSERT INTO customers (full_name, email, phone, address, password_hash, {$column}) VALUES (?, ?, '', '', ?, ?)");
                $insert->execute([$name, $email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $providerId]);
                $customer = [
                    'id' => (int)$pdo->lastInsertId(),
                    'full_name' => $name,
                    'email' => $email,
                    'phone' => '',
                    'address' => '',
                    'profile_image' => null,
                ];
            }
        }

        session_regenerate_id(true);
        $_SESSION['customer'] = [
            'id' => (int)$customer['id'],
            'full_name' => $customer['full_name'],
            'email' => $customer['email'],
            'phone' => $customer['phone'] ?? '',
            'address' => $customer['address'] ?? '',
            'profile_image' => $customer['profile_image'] ?? null,
        ];

        if (($pending['buy'] ?? '') !== '') {
            $_SESSION['pending_buy_now'] = [
                'id' => $pending['buy'],
                'quantity' => (int)$pending['quantity'],
            ];
        }

        unset($_SESSION['customer_csrf']);
        
        $redirectTo = ($pending['next'] ?? '') === 'products' ? '../index.php#products' : '../dashboard.php';
        header('Location: ' . $redirectTo);
        exit;

    } catch (Throwable $exception) {
        error_log('Customer social sign in failed (' . $provider . '): ' . $exception->getMessage());
        social_auth_fail($exception instanceof PDOException ? 'Social sign-in is not ready in the database. Import the latest database/schema.sql, then try again.' : $exception->getMessage());
    }
}