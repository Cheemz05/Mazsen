<?php
declare(strict_types=1);
require __DIR__ . '/../includes/customer_auth.php';
$customer = customer_user();
if (!$customer) { header('Location: login.php'); exit; }
require __DIR__ . '/../includes/db.php';
$profileQuery = $pdo->prepare('SELECT full_name, email, phone, address, profile_image FROM customers WHERE id = ? LIMIT 1');
$profileQuery->execute([$customer['id']]);
$savedProfile = $profileQuery->fetch();
if ($savedProfile) {
    $customer = array_merge($customer, $savedProfile);
    $_SESSION['customer'] = $customer;
}
if (empty($_SESSION['customer_settings_csrf'])) $_SESSION['customer_settings_csrf'] = bin2hex(random_bytes(32));
$error = '';
$section = (string)($_GET['section'] ?? $_POST['section'] ?? '');
if (!in_array($section, ['', 'details', 'address', 'password'], true)) $section = '';
$saved = isset($_GET['saved']) || isset($_GET['photo_saved']);
$uploadedProfileImage = null;
$oldProfileImage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_profile_picture') {
    if (!hash_equals($_SESSION['customer_settings_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Your session expired. Refresh the page and choose your photo again.';
    } else {
        try {
            $lookup = $pdo->prepare('SELECT profile_image FROM customers WHERE id = ? LIMIT 1');
            $lookup->execute([$customer['id']]);
            $account = $lookup->fetch();
            if (!$account) throw new RuntimeException('Your account could not be found. Please sign in again.');
            $oldProfileImage = $account['profile_image'] ?? null;
            $uploadedProfileImage = save_customer_profile_picture($_FILES['profile_picture'] ?? [], (int)$customer['id']);
            $update = $pdo->prepare('UPDATE customers SET profile_image = ? WHERE id = ?');
            $update->execute([$uploadedProfileImage, $customer['id']]);
            $_SESSION['customer']['profile_image'] = $uploadedProfileImage;
            if ($oldProfileImage !== null) delete_customer_profile_picture($oldProfileImage);
            $_SESSION['customer_settings_csrf'] = bin2hex(random_bytes(32));
            header('Location: settings.php?photo_saved=1');
            exit;
        } catch (PDOException $exception) {
            if ($uploadedProfileImage !== null) delete_customer_profile_picture($uploadedProfileImage);
            $error = 'Your photo could not be saved. Please try again.';
        } catch (RuntimeException $exception) {
            if ($uploadedProfileImage !== null) delete_customer_profile_picture($uploadedProfileImage);
            $error = $exception->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section !== '') {
    if (!hash_equals($_SESSION['customer_settings_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            if ($section === 'details') {
                $fullName = trim((string)($_POST['full_name'] ?? ''));
                $email = strtolower(trim((string)($_POST['email'] ?? '')));
                $phone = trim((string)($_POST['phone'] ?? ''));
                if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) throw new RuntimeException('Enter your full name (2 to 120 characters).');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) throw new RuntimeException('Enter a valid email address.');
                if (!preg_match('/^[0-9+() .-]{7,30}$/', $phone)) throw new RuntimeException('Enter a valid phone number.');
                $update = $pdo->prepare('UPDATE customers SET full_name = ?, email = ?, phone = ? WHERE id = ?');
                $update->execute([$fullName, $email, $phone, $customer['id']]);
                $customer = array_merge($customer, ['full_name'=>$fullName, 'email'=>$email, 'phone'=>$phone]);
            } elseif ($section === 'address') {
                $address = trim((string)($_POST['address'] ?? ''));
                if (mb_strlen($address) < 8 || mb_strlen($address) > 500) throw new RuntimeException('Enter your complete delivery address.');
                $update = $pdo->prepare('UPDATE customers SET address = ? WHERE id = ?');
                $update->execute([$address, $customer['id']]);
                $customer['address'] = $address;
            } else {
                $currentPassword = (string)($_POST['current_password'] ?? '');
                $newPassword = (string)($_POST['new_password'] ?? '');
                $confirmation = (string)($_POST['password_confirmation'] ?? '');
                if ($currentPassword === '') throw new RuntimeException('Enter your current password.');
                if (strlen($newPassword) < 8) throw new RuntimeException('Your new password must have at least 8 characters.');
                if ($newPassword !== $confirmation) throw new RuntimeException('The new passwords do not match.');
                $lookup = $pdo->prepare('SELECT password_hash FROM customers WHERE id = ? LIMIT 1');
                $lookup->execute([$customer['id']]);
                $account = $lookup->fetch();
                if (!$account || !password_verify($currentPassword, $account['password_hash'])) throw new RuntimeException('Your current password is incorrect.');
                $update = $pdo->prepare('UPDATE customers SET password_hash = ? WHERE id = ?');
                $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $customer['id']]);
            }
            $_SESSION['customer'] = $customer;
            $_SESSION['customer_settings_csrf'] = bin2hex(random_bytes(32));
            header('Location: settings.php?section=' . rawurlencode($section) . '&saved=1');
            exit;
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000' ? 'That email address is already used by another account.' : 'Your information could not be saved. Please try again.';
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section === '' && ($_POST['action'] ?? '') !== 'upload_profile_picture') {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');

    if (!hash_equals($_SESSION['customer_settings_csrf'], (string)($_POST['csrf'] ?? ''))) $error = 'Your session expired. Refresh the page and try again.';
    elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) $error = 'Enter your full name (2 to 120 characters).';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $error = 'Enter a valid email address.';
    elseif (!preg_match('/^[0-9+() .-]{7,30}$/', $phone)) $error = 'Enter a valid phone number.';
    elseif (mb_strlen($address) < 8 || mb_strlen($address) > 500) $error = 'Enter your complete delivery address.';
    elseif ($newPassword !== '' && $currentPassword === '') $error = 'Enter your current password to set a new password.';
    elseif ($newPassword !== '' && strlen($newPassword) < 8) $error = 'Your new password must have at least 8 characters.';
    elseif ($newPassword !== '' && $newPassword !== $confirmation) $error = 'The new passwords do not match.';
    else {
        try {
            $lookup = $pdo->prepare('SELECT password_hash,profile_image FROM customers WHERE id = ? LIMIT 1');
            $lookup->execute([$customer['id']]);
            $account = $lookup->fetch();
            if (!$account) throw new RuntimeException('Your account could not be found. Please sign in again.');
            if ($newPassword !== '' && !password_verify($currentPassword, $account['password_hash'])) throw new RuntimeException('Your current password is incorrect.');

            $oldProfileImage = $account['profile_image'] ?? null;
            $profileImage = $oldProfileImage;
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                $profileImage = save_customer_profile_picture($_FILES['profile_picture'], (int)$customer['id']);
                $uploadedProfileImage = $profileImage;
            }

            if ($newPassword !== '') {
                $update = $pdo->prepare('UPDATE customers SET full_name = ?, email = ?, phone = ?, address = ?, profile_image = ?, password_hash = ? WHERE id = ?');
                $update->execute([$fullName, $email, $phone, $address, $profileImage, password_hash($newPassword, PASSWORD_DEFAULT), $customer['id']]);
            } else {
                $update = $pdo->prepare('UPDATE customers SET full_name = ?, email = ?, phone = ?, address = ?, profile_image = ? WHERE id = ?');
                $update->execute([$fullName, $email, $phone, $address, $profileImage, $customer['id']]);
            }
            $_SESSION['customer'] = ['id'=>(int)$customer['id'], 'full_name'=>$fullName, 'email'=>$email, 'phone'=>$phone, 'address'=>$address, 'profile_image'=>$profileImage];
            $customer = $_SESSION['customer'];
            if ($uploadedProfileImage !== null && $oldProfileImage !== null) delete_customer_profile_picture($oldProfileImage);
            $_SESSION['customer_settings_csrf'] = bin2hex(random_bytes(32));
            $saved = true;
        } catch (PDOException $exception) {
            if ($uploadedProfileImage !== null) delete_customer_profile_picture($uploadedProfileImage);
            $error = $exception->getCode() === '23000' ? 'That email address is already used by another account.' : 'Your information could not be saved. Please try again.';
        } catch (RuntimeException $exception) {
            if ($uploadedProfileImage !== null) delete_customer_profile_picture($uploadedProfileImage);
            $error = $exception->getMessage();
        }
    }
}

function settings_h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function save_customer_profile_picture(array $file, int $customerId): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        if (($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('The profile picture must be 2 MB or smaller.');
        throw new RuntimeException('The profile picture could not be uploaded. Please try again.');
    }
    if (($file['size'] ?? 0) < 1 || $file['size'] > 2 * 1024 * 1024 || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Choose an image no larger than 2 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $imageInfo = @getimagesize($file['tmp_name']);
    $extensions = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
    if (!isset($extensions[$mime]) || !$imageInfo || $imageInfo['mime'] !== $mime || $imageInfo[0] > 6000 || $imageInfo[1] > 6000) {
        throw new RuntimeException('Use a valid JPG, PNG, or WebP profile picture (up to 6000 × 6000 pixels).');
    }
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'customer-profiles';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('The profile picture folder is unavailable.');
    $relativePath = 'uploads/customer-profiles/' . bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath))) {
        throw new RuntimeException('The profile picture could not be saved. Please try again.');
    }
    return $relativePath;
}
function delete_customer_profile_picture(mixed $relativePath): void {
    if (!is_string($relativePath) || !preg_match('~^uploads/customer-profiles/[a-f0-9]{40}\.(jpg|png|webp)$~', $relativePath)) return;
    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($fullPath)) @unlink($fullPath);
}
function customer_settings_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) return '?';
    $initials = mb_substr($parts[0], 0, 1);
    if (count($parts) > 1) $initials .= mb_substr($parts[count($parts) - 1], 0, 1);
    return mb_strtoupper($initials, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f7f4fa">
  <title>Account settings | MazSen Munch &amp; Sip</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../assets/css/styles.css?v=124">
  
  <link rel="stylesheet" href="../assets/css/logout.css?v=1">
  <style>
    .settings-page{max-width:850px;margin:0 auto;padding:34px 22px 60px}.settings-top{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:24px}.settings-top h1{margin:0;font-size:30px;font-weight:800;letter-spacing:-.04em;color:#302a23}.settings-top p{margin:6px 0 0;color:#81786e;font-size:13px}.settings-card{padding:26px;border:1px solid #eee9e1;border-radius:18px;background:#fff;box-shadow:0 12px 36px #4335220b}.settings-section-title{margin:0 0 16px;font-size:16px;font-weight:750;color:#342e26}.settings-field{display:grid;gap:7px;color:#625b51;font-size:12px;font-weight:650}.settings-field input,.settings-field textarea{width:100%;border:1px solid #e8e3dc;border-radius:10px;padding:12px 13px;background:#fff;color:#302a23;font:inherit;font-size:14px;outline:none}.settings-field input:focus,.settings-field textarea:focus{border-color:#a183a0;box-shadow:0 0 0 3px #a183a022}.settings-message{margin:0 0 18px;padding:12px 14px;border-radius:10px;font-size:13px}.settings-message.error{background:#fff0ed;color:#9a443b}.settings-message.success{background:#edf7ef;color:#5e3965}.settings-password{margin-top:26px;padding-top:24px;border-top:1px solid #eee9e1}.settings-hint{margin:0 0 14px;color:#81786e;font-size:12px;line-height:1.55}.settings-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:23px}.settings-logout{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 15px;border:1px solid #e9ddd9;border-radius:10px;color:#9a5148;text-decoration:none;font-size:13px;font-weight:650}.settings-logout:hover{background:#fff5f3}.settings-back-link{display:inline-grid;place-items:center;width:44px;height:44px;border:1px solid #ded0e0;border-radius:12px;background:#f5faf6;color:#61336b;text-decoration:none;transition:background-color .16s ease,transform .16s ease}.settings-back-link:hover{background:#eaf5ed;transform:translateX(-2px)}.settings-back-link svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .profile-picture-row{display:flex;align-items:center;gap:18px;margin:0 0 23px;padding:17px;border:1px solid #e5eee6;border-radius:14px;background:#f8fbf8}.profile-picture-avatar{display:grid;place-items:center;width:78px;height:78px;flex:none;overflow:hidden;border:3px solid #fff;border-radius:50%;background:#e6f2e8;color:#693e71;font-size:20px;font-weight:800;box-shadow:0 0 0 1px #dbe8dc}.profile-picture-avatar img{width:100%;height:100%;object-fit:cover}.profile-picture-copy{min-width:0;flex:1}.profile-picture-copy strong{display:block;margin-bottom:4px;color:#443347;font-size:14px}.profile-picture-copy p{margin:0 0 10px;color:#77857a;font-size:12px}.profile-upload-control{position:relative;display:flex;align-items:center;gap:12px;min-height:48px;max-width:100%;overflow:hidden;padding:6px 12px;border:1px solid #dce8de;border-radius:11px;background:#fff;color:#66766a;cursor:pointer;transition:border-color .16s ease,box-shadow .16s ease,background-color .16s ease}.profile-upload-control:hover{border-color:#bda2c0;background:#fcfefc}.profile-upload-control:focus-within{border-color:#996ea1;box-shadow:0 0 0 3px #692f7820}.profile-upload-action{display:inline-flex;align-items:center;gap:7px;flex:none;min-height:34px;padding:0 11px;border-radius:8px;background:#eaf5ed;color:#61316c;font-size:13px;font-weight:750}.profile-upload-action svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.profile-upload-name{overflow:hidden;color:#718076;font-size:13px;text-overflow:ellipsis;white-space:nowrap}.profile-picture-input{position:absolute;inset:0;width:100%;height:100%;margin:0;padding:0;border:0;opacity:0;cursor:pointer}.profile-picture-note{display:block;margin-top:7px;color:#88958b;font-size:11px}
    .settings-profile-summary,.settings-mobile-menu{display:none}
    .settings-bottom-nav{display:none}
    .settings-subpage-heading{display:flex;align-items:center;gap:13px;margin:0 0 18px}
    .settings-subpage-heading a{display:grid;place-items:center;width:40px;height:40px;flex:none;border:1px solid #dce7dd;border-radius:50%;color:#64366e;text-decoration:none;font-size:21px}
    .settings-subpage-heading h2{margin:0;color:#29372d;font-size:21px;font-weight:750}
    @media(max-width:600px){
      .settings-page{max-width:470px;min-height:100svh;padding:20px 14px 34px}
      .settings-top{align-items:center;flex-direction:row;margin:0 3px 14px}
      .settings-top .eyebrow,.settings-top h1,.settings-top>div>p:last-child{display:none}
      .settings-back-link{width:40px;height:40px;border-radius:50%}
      .settings-card{padding:18px 16px 20px;border-radius:22px}
      .settings-profile-summary{display:flex;align-items:center;flex-direction:column;text-align:center;padding:6px 0 17px}
      .settings-subpage-heading{margin:0 2px 13px}
      .settings-subpage-heading h2{font-size:19px}
      .settings-page.is-subpage .settings-top{display:none}
      .settings-page.is-subpage .settings-card{padding-top:19px}
      .settings-page:not(.is-subpage) .settings-full-form{display:none}
      .settings-page.show-full-form .settings-full-form{display:block}
      .settings-profile-avatar{display:grid;place-items:center;width:100px;height:100px;overflow:hidden;border:3px solid #fff;border-radius:50%;background:#e9f3eb;color:#61336b;font-size:27px;font-weight:800;box-shadow:0 0 0 2px #d8e6da,0 8px 20px #20362918}
      .settings-profile-avatar img{width:100%;height:100%;object-fit:cover}
      .settings-profile-summary h1{margin:12px 0 3px;color:#282d29;font-size:21px;font-weight:750;letter-spacing:-.03em}
      .settings-profile-summary p{margin:0;color:#778079;font-size:13px;line-height:1.5}
      .settings-profile-summary .settings-profile-phone{margin-top:2px;font-size:12px}
      .settings-mobile-menu{display:block;margin:0 0 19px;padding:13px 15px;border:1px solid #eceeea;border-radius:16px;background:#fff}
      .settings-mobile-menu h2{margin:0 0 5px;color:#667269;font-size:11px;font-weight:750;letter-spacing:.08em;text-transform:uppercase}
      .settings-mobile-menu a{display:flex;align-items:center;gap:12px;min-height:48px;border-bottom:1px solid #eff1ee;color:#303832;text-decoration:none;font-size:13px;font-weight:550}
      .settings-mobile-menu a:last-child{border-bottom:0}
      .settings-mobile-menu a:active{color:#692876}
      .settings-menu-icon{display:grid;place-items:center;width:29px;height:29px;flex:none;border-radius:9px;background:#f0f6f1;color:#6c3778}
      .settings-menu-icon svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
      .settings-menu-arrow{margin-left:auto;color:#9aa39b;font-size:18px}
      .profile-picture-row{gap:11px;margin-bottom:20px;padding:12px;border-radius:13px}
      .profile-picture-row{display:none}
      .profile-picture-row>.profile-picture-avatar{width:48px;height:48px;border-width:2px;font-size:15px}
      .profile-picture-copy strong{font-size:13px}
      .profile-picture-copy p,.profile-picture-note{font-size:11px}
      .profile-picture-copy p{margin-bottom:8px}
      .profile-upload-control{min-height:40px;gap:7px;padding:4px 7px}
      .profile-upload-action{min-height:30px;padding:0 8px;font-size:12px}
      .profile-upload-name{font-size:11px}
      .settings-section-title{margin-bottom:8px;font-size:16px}
      .settings-hint{margin-bottom:13px;font-size:12px}
      .settings-field{gap:6px;font-size:12px}
      .settings-field input,.settings-field textarea{min-height:46px;padding:11px 13px;border-radius:12px;font-size:14px}
      .settings-field textarea{min-height:88px}
      .settings-password{margin-top:22px;padding-top:20px}
      .settings-actions{flex-direction:column-reverse;margin-top:20px}
      .settings-actions>*{width:100%;min-height:47px}
      #personal-information,#delivery-address,.settings-password{scroll-margin-top:18px}
      .settings-bottom-nav{position:fixed;z-index:60;right:12px;bottom:calc(9px + env(safe-area-inset-bottom,0px));left:12px;display:flex;align-items:center;justify-content:space-around;gap:5px;min-height:62px;padding:6px 8px;border:1px solid #e4ebe5;border-radius:22px;background:rgba(255,255,255,.95);box-shadow:0 8px 28px rgba(60, 42, 62,.15);-webkit-backdrop-filter:blur(16px);backdrop-filter:blur(16px)}
      .settings-bottom-nav a{display:flex;flex:1;min-width:0;height:48px;align-items:center;justify-content:center;flex-direction:column;gap:2px;border-radius:14px;color:#718478;text-decoration:none;font-size:9px;font-weight:600;line-height:1}
      .settings-bottom-nav a svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
      .settings-bottom-nav a.active{background:#eaf3eb;color:#613c67}
      .settings-page{padding-bottom:105px}
    }
    @media(min-width:601px){
      .settings-page{position:relative;max-width:560px;min-height:100vh;margin:0 auto;padding:24px 22px 48px}
      .settings-top{justify-content:flex-end;margin:0 0 14px}
      .settings-top>div{display:none}
      .settings-back-link{width:40px;height:40px;border-radius:50%;background:#fbf8fc;color:#75439a}
      .settings-card{padding:26px 24px 30px;border-color:#e7deed;border-radius:24px;box-shadow:0 18px 46px rgba(63,37,78,.08)}
      .settings-profile-summary{display:flex;align-items:center;flex-direction:column;padding:0 0 18px;text-align:center}
      .settings-profile-avatar{display:grid;width:92px;height:92px;place-items:center;overflow:hidden;padding:0;border:3px solid #fff;border-radius:50%;background:#eaf3eb;color:#75439a;font-family:inherit;font-size:25px;font-weight:800;line-height:1;cursor:pointer;box-shadow:0 0 0 2px #d9c9e2,0 8px 20px rgba(63,37,78,.1);transition:transform .18s,box-shadow .18s}
      .settings-profile-avatar:hover{transform:translateY(-2px);box-shadow:0 0 0 3px #d9c9e2,0 10px 24px rgba(63,37,78,.15)}
      .settings-profile-avatar:focus-visible{outline:3px solid #75439a;outline-offset:5px}
      .settings-profile-avatar img{width:100%;height:100%;object-fit:cover}
      .settings-profile-summary h1{margin:12px 0 3px;color:#302438;font-size:21px;font-weight:800;letter-spacing:-.03em}
      .settings-profile-summary p{margin:0;color:#766b7d;font-size:13px;line-height:1.5}
      .settings-profile-summary .settings-profile-phone{margin-top:2px;font-size:12px}
      .settings-mobile-menu{display:block;margin:0;padding:13px 14px;border:1px solid #e7deed;border-radius:16px;background:#fff}
      .settings-mobile-menu h2{margin:0 0 5px;color:#5e4c65;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
      .settings-mobile-menu a{display:flex;align-items:center;gap:12px;min-height:48px;border-bottom:1px solid #eee8f1;color:#5d347b;text-decoration:none;font-size:13px;font-weight:600}
      .settings-mobile-menu a:last-child{border-bottom:0}
      .settings-menu-icon{display:grid;width:29px;height:29px;flex:none;place-items:center;border-radius:9px;background:#f0e7f5;color:#75439a}
      .settings-menu-icon svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
      .settings-menu-arrow{margin-left:auto;color:#9a8aa0;font-size:18px}
      .settings-page:not(.is-subpage) .settings-full-form{display:none}
    }
    @media(max-width:600px){
      .settings-page.is-subpage{padding:11px 10px 86px}
      .settings-page.is-subpage .settings-subpage-heading{position:relative;justify-content:center;gap:9px;min-height:34px;margin:14px 1px 9px}
      .settings-page.is-subpage .settings-subpage-heading a{position:absolute;left:0;width:34px;height:34px;font-size:18px}
      .settings-page.is-subpage .settings-subpage-heading h2{font-size:17px;letter-spacing:-.02em;text-align:center}
      .settings-page.is-subpage .settings-card{padding:14px 13px 15px;border-radius:18px}
      .settings-page.is-subpage .settings-hint{margin:0 0 10px;font-size:11px;line-height:1.4}
      .settings-page.is-subpage form>.grid{gap:10px!important}
      .settings-page.is-subpage .settings-field{gap:4px;font-size:10px}
      .settings-page.is-subpage .settings-field input,
      .settings-page.is-subpage .settings-field textarea{min-height:39px;padding:8px 11px;border-radius:10px;font-size:12px}
      .settings-page.is-subpage .settings-actions{gap:7px;margin-top:12px}
      .settings-page.is-subpage .settings-actions>*{min-height:39px;font-size:11px}
    }
  </style>
</head>
<body class="min-h-screen antialiased">
  <main class="settings-page<?= $section !== '' ? ' is-subpage' : '' ?>">
    <header class="settings-top"><div><p class="eyebrow">MAZSEN MUNCH &amp; SIP</p><h1>Account settings</h1><p>Update the contact and delivery information used for your orders.</p></div><a class="settings-back-link" href="../index.php" aria-label="Back to store" title="Back to store"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></a></header>
    <?php if ($section !== ''): ?><div class="settings-subpage-heading"><a href="settings.php" aria-label="Back to account settings">‹</a><h2><?= $section === 'details' ? 'Account details' : ($section === 'address' ? 'Delivery address' : 'Change password') ?></h2></div><?php endif; ?>
    <section class="settings-card">
      <?php if ($section === ''): ?><div class="settings-profile-summary">
        <button class="settings-profile-avatar" id="profile-avatar-display" type="button" aria-label="Choose a profile photo" title="Choose a profile photo"><?php if (!empty($customer['profile_image'])): ?><img src="../<?= settings_h($customer['profile_image']) ?>" alt=""><?php else: ?><?= settings_h(customer_settings_initials((string)$customer['full_name'])) ?><?php endif; ?></button>
        <h1><?= settings_h($customer['full_name']) ?></h1>
        <p><?= settings_h($customer['email']) ?></p>
        <p class="settings-profile-phone"><?= settings_h($customer['phone']) ?></p>
      </div>
      <nav class="settings-mobile-menu" aria-label="Account settings shortcuts">
        <h2>Profile</h2>
        <a href="settings.php?section=details"><span class="settings-menu-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20v-1.5a7 7 0 0 1 14 0V20"/></svg></span>Account details<span class="settings-menu-arrow" aria-hidden="true">›</span></a>
        <a href="../index.php#orders"><span class="settings-menu-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 7v5l3 2"/><circle cx="12" cy="12" r="9"/></svg></span>Order history<span class="settings-menu-arrow" aria-hidden="true">›</span></a>
        <a href="settings.php?section=address"><span class="settings-menu-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2"/></svg></span>Delivery address<span class="settings-menu-arrow" aria-hidden="true">›</span></a>
        <a href="settings.php?section=password"><span class="settings-menu-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3m-4 4v3"/></svg></span>Change password<span class="settings-menu-arrow" aria-hidden="true">›</span></a>
      </nav>
      <?php endif; ?>
      <?php if ($error): ?><p class="settings-message error" role="alert"><?= settings_h($error) ?></p><?php endif; ?>
      <?php if ($saved): ?><p class="settings-message success" role="status">Your account information has been updated.</p><?php endif; ?>
      <?php if ($section === ''): ?><form class="settings-full-form" method="post" action="settings.php" autocomplete="on" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= settings_h($_SESSION['customer_settings_csrf']) ?>">
        <div class="profile-picture-row"><span class="profile-picture-avatar" id="profile-picture-preview"><?php if (!empty($customer['profile_image'])): ?><img src="../<?= settings_h($customer['profile_image']) ?>" alt="Customer profile picture"><?php else: ?><?= settings_h(customer_settings_initials((string)$customer['full_name'])) ?><?php endif; ?></span><div class="profile-picture-copy"><strong>Profile picture</strong><p>Show your photo beside your name on your MazSen account.</p><label class="profile-upload-control" for="profile-picture-input"><span class="profile-upload-action"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-5"/></svg>Choose photo</span><span class="profile-upload-name" id="profile-picture-filename">No file selected</span><input id="profile-picture-input" class="profile-picture-input" type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-picture-note"></label><span class="profile-picture-note" id="profile-picture-note">JPG, PNG, or WebP · Up to 2 MB</span></div></div>
        <h2 class="settings-section-title" id="personal-information">Personal information</h2><p class="settings-hint">Edit any field below, then choose Save changes to update your account.</p>
        <div class="grid gap-4 md:grid-cols-2">
          <label class="settings-field md:col-span-2">Full name<input name="full_name" autocomplete="name" maxlength="120" value="<?= settings_h($customer['full_name']) ?>" required></label>
          <label class="settings-field">Email address<input type="email" name="email" autocomplete="email" maxlength="190" value="<?= settings_h($customer['email']) ?>" required></label>
          <label class="settings-field">Phone number<input type="tel" name="phone" autocomplete="tel" maxlength="30" value="<?= settings_h($customer['phone']) ?>" required></label>
          <label class="settings-field md:col-span-2" id="delivery-address">Complete delivery address<textarea name="address" autocomplete="street-address" maxlength="500" rows="3" required><?= settings_h($customer['address']) ?></textarea></label>
        </div>
        <div class="settings-password" id="change-password"><h2 class="settings-section-title">Change password</h2><p class="settings-hint">Leave these fields blank if you do not want to change your password. Your current password is required to set a new one.</p><div class="grid gap-4 md:grid-cols-2">
          <label class="settings-field md:col-span-2">Current password<input type="password" name="current_password" autocomplete="current-password"></label>
          <label class="settings-field">New password<input type="password" name="new_password" autocomplete="new-password" minlength="8"><small class="font-normal text-gray-500">At least 8 characters</small></label>
          <label class="settings-field">Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8"></label>
        </div></div>
        <div class="settings-actions"><a class="settings-logout" href="logout.php">Log out</a><button class="primary-button justify-center" type="submit">Save changes</button></div>
      </form>
      <?php else: ?>
      <form method="post" action="settings.php?section=<?= settings_h($section) ?>" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= settings_h($_SESSION['customer_settings_csrf']) ?>">
        <input type="hidden" name="section" value="<?= settings_h($section) ?>">
        <?php if ($section === 'details'): ?>
          <p class="settings-hint">Keep your contact information up to date for your orders.</p>
          <div class="grid gap-4">
            <label class="settings-field">Full name<input name="full_name" autocomplete="name" maxlength="120" value="<?= settings_h($customer['full_name']) ?>" required></label>
            <label class="settings-field">Email address<input type="email" name="email" autocomplete="email" maxlength="190" value="<?= settings_h($customer['email']) ?>" required></label>
            <label class="settings-field">Phone number<input type="tel" name="phone" autocomplete="tel" maxlength="30" value="<?= settings_h($customer['phone']) ?>" required></label>
          </div>
        <?php elseif ($section === 'address'): ?>
          <p class="settings-hint">This address will be used to deliver your orders.</p>
          <label class="settings-field">Complete delivery address<textarea name="address" autocomplete="street-address" maxlength="500" rows="5" required><?= settings_h($customer['address']) ?></textarea></label>
        <?php else: ?>
          <p class="settings-hint">Choose a new password with at least 8 characters.</p>
          <div class="grid gap-4">
            <label class="settings-field">Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
            <label class="settings-field">New password<input type="password" name="new_password" autocomplete="new-password" minlength="8" required></label>
            <label class="settings-field">Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
          </div>
        <?php endif; ?>
        <div class="settings-actions"><a class="settings-logout" href="settings.php">Back to profile</a><button class="primary-button justify-center" type="submit">Save changes</button></div>
      </form>
      <?php endif; ?>
    </section>
</main>
<nav class="settings-bottom-nav" aria-label="Main navigation">
  <a href="../index.php#dashboard" aria-label="Dashboard"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/></svg><span>Dashboard</span></a>
  <a href="../index.php#products" aria-label="Order menu"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v7M4 3v4a3 3 0 0 0 6 0V3M7 10v11M16 3v18m0-18c3 2 4 5 4 9h-4"/></svg><span>Menu</span></a>
  <a href="../index.php#favorites" aria-label="Favorites"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.8c0 5.3-8.8 11-8.8 11s-8.8-5.7-8.8-11A4.8 4.8 0 0 1 12 6.1a4.8 4.8 0 0 1 8.8 2.7Z"/></svg><span>Favorites</span></a>
  <a href="../index.php#cart" aria-label="Cart"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h8.7a2 2 0 0 0 2-1.6L22 8H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg><span>Cart</span></a>
  <a class="active" href="settings.php" aria-current="page" aria-label="Profile"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg><span>Profile</span></a>
</nav>
<script>
if (window.location.hash === '#delivery-address') document.querySelector('.settings-page')?.classList.add('show-full-form');
const profileInput = document.querySelector('input[name="profile_picture"]');
const profilePreview = document.getElementById('profile-picture-preview');
const profileAvatarDisplay = document.getElementById('profile-avatar-display');
const profileFilename = document.getElementById('profile-picture-filename');
let profilePreviewUrl = null;
profileAvatarDisplay?.addEventListener('click', () => profileInput?.click());
profileInput?.addEventListener('change', () => {
  const file = profileInput.files?.[0];
  if (profileFilename) profileFilename.textContent = file?.name || 'No file selected';
  if (!file) return;
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
    alert('Choose a JPG, PNG, or WebP photo that is 2 MB or smaller.');
    profileInput.value = '';
    if (profileFilename) profileFilename.textContent = 'No file selected';
    return;
  }
  if (profilePreviewUrl) URL.revokeObjectURL(profilePreviewUrl);
  profilePreviewUrl = URL.createObjectURL(file);
  [profilePreview, profileAvatarDisplay].forEach(preview => preview?.replaceChildren(Object.assign(document.createElement('img'), { src: profilePreviewUrl, alt: 'Selected profile picture preview' })));
  const form = profileInput.form;
  if (!form) return;
  let action = form.querySelector('input[name="action"]');
  if (!action) {
    action = document.createElement('input');
    action.type = 'hidden';
    action.name = 'action';
    form.append(action);
  }
  action.value = 'upload_profile_picture';
  form.submit();
});
</script>
<script src="../assets/js/logout-transition.js?v=1"></script>
</body>
</html>
