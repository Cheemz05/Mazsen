<?php
declare(strict_types=1);

require_once __DIR__ . '/customer_auth.php';
require_once __DIR__ . '/db.php';

function social_auth_config(string $provider): array
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $origin = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/Mazsen Munch/customer/social-auth.php'));
    $base = preg_replace('~/customer/[^/]+$~', '', $script) ?: '';
    $base = str_replace(' ', '%20', $base);
    $callback = $origin . $base . '/customer/' . ($provider === 'google' ? 'google-auth.php' : 'facebook-auth.php');
    if ($provider === 'google') return ['id' => (string)(getenv('MAZSEN_GOOGLE_CLIENT_ID') ?: ''), 'secret' => (string)(getenv('MAZSEN_GOOGLE_CLIENT_SECRET') ?: ''), 'callback' => $callback];
    return ['id' => (string)(getenv('MAZSEN_FACEBOOK_APP_ID') ?: ''), 'secret' => (string)(getenv('MAZSEN_FACEBOOK_APP_SECRET') ?: ''), 'callback' => $callback];
}

function social_auth_http(string $url, ?array $post = null, ?string $bearerToken = null): array
{
    $headers = ['Accept: application/json'];
    if ($bearerToken !== null) $headers[] = 'Authorization: Bearer ' . $bearerToken;
    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $headers]);
        if ($post !== null) {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        }
        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if (!is_string($body) || $status < 200 || $status >= 300) throw new RuntimeException('Provider request failed.');
    } else {
        $options = ['http' => ['method' => $post === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers) . ($post === null ? '' : "\r\nContent-Type: application/x-www-form-urlencoded"), 'timeout' => 15, 'ignore_errors' => true]];
        if ($post !== null) $options['http']['content'] = http_build_query($post);
        $body = @file_get_contents($url, false, stream_context_create($options));
        if (!is_string($body)) throw new RuntimeException('Provider request failed.');
    }
    $data = json_decode($body, true);
    if (!is_array($data)) throw new RuntimeException('Provider returned an invalid response.');
    return $data;
}

function social_auth_fail(string $message): never
{
    echo '<div style="padding: 30px; background: #fee; color: #900; font-family: sans-serif; font-size: 18px; border: 2px solid #c99; margin: 40px; border-radius: 8px;">';
    echo '<strong>Social Auth Failed Here:</strong><br><br>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '</div>';
    exit;
}

function social_auth_controller(string $provider): never
{
    global $pdo;
    if (!in_array($provider, ['google', 'facebook'], true)) social_auth_fail('That sign-in option is unavailable.');
    $config = social_auth_config($provider);
    if ($config['id'] === '' || $config['secret'] === '') social_auth_fail(ucfirst($provider) . ' sign-in is not configured yet. Add the app credentials in the server environment and try again.');

    if (!isset($_GET['code']) && !isset($_GET['error'])) {
        $state = bin2hex(random_bytes(32));
        $next = ($_GET['next'] ?? '') === 'products' ? 'products' : 'dashboard';
        $buy = (string)($_GET['buy'] ?? '');
        if (!preg_match('/^[a-z0-9-]{1,80}$/', $buy)) $buy = '';
        $quantity = filter_var($_GET['qty'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]) ?: 1;
        $_SESSION['customer_social_oauth'] = ['provider' => $provider, 'state' => $state, 'next' => $next, 'buy' => $buy, 'quantity' => $quantity, 'created' => time()];
        if ($provider === 'google') {
            $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(['client_id' => $config['id'], 'redirect_uri' => $config['callback'], 'response_type' => 'code', 'scope' => 'openid email profile', 'state' => $state, 'prompt' => 'select_account']);
        } else {
            $url = 'https://www.facebook.com/dialog/oauth?' . http_build_query(['client_id' => $config['id'], 'redirect_uri' => $config['callback'], 'response_type' => 'code', 'scope' => 'email,public_profile', 'state' => $state]);
        }
        header('Location: ' . $url);
        exit;
    }

    $pending = $_SESSION['customer_social_oauth'] ?? null;
unset($_SESSION['customer_social_oauth']);

if (isset($_GET['error']) || !is_array($pending) || ($pending['provider'] ?? '') !== $provider || !hash_equals((string)($pending['state'] ?? ''), (string)($_GET['state'] ?? ''))) {
    $err = 'Unknown';
    if (isset($_GET['error'])) $err = 'OAuth Provider Error: ' . $_GET['error'];
    elseif (!is_array($pending)) $err = 'Session expired or cookie lost (Session ID: ' . session_id() . ')';
    elseif (($pending['provider'] ?? '') !== $provider) $err = 'Provider mismatch: expected ' . $provider . ', got ' . ($pending['provider'] ?? '');
    elseif (!hash_equals((string)($pending['state'] ?? ''), (string)($_GET['state'] ?? ''))) $err = 'CSRF State mismatch! Session state vs GET state failed.';

    echo '<div style="padding: 20px; background: #fee; color: #900; font-family: monospace; border: 1px solid #c99; margin: 20px; border-radius: 5px;">';
    echo '<strong>OAuth Validation Failed:</strong> ' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8');
    echo '</div>';
    exit;
}
    try {
        if ($provider === 'google') {
            $tokens = social_auth_http('https://oauth2.googleapis.com/token', ['code' => (string)$_GET['code'], 'client_id' => $config['id'], 'client_secret' => $config['secret'], 'redirect_uri' => $config['callback'], 'grant_type' => 'authorization_code']);
            if (empty($tokens['access_token'])) throw new RuntimeException('Could not finish Google sign-in.');
            $profile = social_auth_http('https://openidconnect.googleapis.com/v1/userinfo', null, (string)$tokens['access_token']);
            if (($profile['email_verified'] ?? false) !== true && ($profile['email_verified'] ?? '') !== 'true') throw new RuntimeException('Google must confirm your email address before you can sign in.');
            $providerId = (string)($profile['sub'] ?? '');
        } else {
            $tokens = social_auth_http('https://graph.facebook.com/v26.0/oauth/access_token?' . http_build_query(['client_id' => $config['id'], 'client_secret' => $config['secret'], 'redirect_uri' => $config['callback'], 'code' => (string)$_GET['code']]));
            if (empty($tokens['access_token'])) throw new RuntimeException('Could not finish Facebook sign-in.');
            $profile = social_auth_http('https://graph.facebook.com/v26.0/me?fields=id,name,email', null, (string)$tokens['access_token']);
            $providerId = (string)($profile['id'] ?? '');
            if (empty($profile['email'])) throw new RuntimeException('Facebook did not share an email address. Allow email access or use email sign-up.');
        }
        $email = strtolower(trim((string)($profile['email'] ?? '')));
        $name = trim((string)($profile['name'] ?? ''));
        if ($providerId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') throw new RuntimeException('The provider did not return the account details needed to sign in.');

        $column = $provider === 'google' ? 'google_sub' : 'facebook_id';
        $find = $pdo->prepare("SELECT id,full_name,email,phone,address,profile_image FROM customers WHERE {$column}=? LIMIT 1");
        $find->execute([$providerId]);
        $customer = $find->fetch();
        if (!$customer) {
            $find = $pdo->prepare('SELECT id,full_name,email,phone,address,profile_image FROM customers WHERE email=? LIMIT 1');
            $find->execute([$email]);
            $customer = $find->fetch();
            if ($customer) {
                $link = $pdo->prepare("UPDATE customers SET {$column}=? WHERE id=? AND ({$column} IS NULL OR {$column}=?)");
                $link->execute([$providerId, $customer['id'], $providerId]);
                if ($link->rowCount() === 0) throw new RuntimeException('This email is already linked to a different social account. Sign in with your existing method.');
            } else {
                $insert = $pdo->prepare("INSERT INTO customers (full_name,email,phone,address,password_hash,{$column}) VALUES (?,?, '', '', ?, ?)");
                $insert->execute([$name, $email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $providerId]);
                $customer = ['id' => (int)$pdo->lastInsertId(), 'full_name' => $name, 'email' => $email, 'phone' => '', 'address' => '', 'profile_image' => null];
            }
        }
        session_regenerate_id(true);
        $_SESSION['customer'] = ['id' => (int)$customer['id'], 'full_name' => $customer['full_name'], 'email' => $customer['email'], 'phone' => $customer['phone'], 'address' => $customer['address'], 'profile_image' => $customer['profile_image']];
        if (($pending['buy'] ?? '') !== '') $_SESSION['pending_buy_now'] = ['id' => $pending['buy'], 'quantity' => (int)$pending['quantity']];
        unset($_SESSION['customer_csrf']);
        header('Location: ../index.php#' . (($pending['next'] ?? '') === 'products' ? 'products' : 'dashboard'));
        exit;
} catch (Throwable $exception) {
    echo '<div style="padding: 20px; background: #fee; color: #900; font-family: monospace; border: 1px solid #c99; margin: 20px; border-radius: 5px;">';
    echo '<strong>OAuth Error Caught:</strong> ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '</div>';
    exit;
}
}