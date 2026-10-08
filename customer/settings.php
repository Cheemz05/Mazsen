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
$saved = false;
$uploadedProfileImage = null;
$oldProfileImage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
  <meta name="theme-color" content="#f7f3eb">
  <title>Account settings | MazSen Munch &amp; Sip</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="stylesheet" href="../assets/css/logout.css?v=1">
  <style>
    .settings-page{max-width:850px;margin:0 auto;padding:34px 22px 60px}.settings-top{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:24px}.settings-top h1{margin:0;font-size:30px;font-weight:800;letter-spacing:-.04em;color:#302a23}.settings-top p{margin:6px 0 0;color:#81786e;font-size:13px}.settings-card{padding:26px;border:1px solid #eee9e1;border-radius:18px;background:#fff;box-shadow:0 12px 36px #4335220b}.settings-section-title{margin:0 0 16px;font-size:16px;font-weight:750;color:#342e26}.settings-field{display:grid;gap:7px;color:#625b51;font-size:12px;font-weight:650}.settings-field input,.settings-field textarea{width:100%;border:1px solid #e8e3dc;border-radius:10px;padding:12px 13px;background:#fff;color:#302a23;font:inherit;font-size:14px;outline:none}.settings-field input:focus,.settings-field textarea:focus{border-color:#8aa77a;box-shadow:0 0 0 3px #8aa77a22}.settings-message{margin:0 0 18px;padding:12px 14px;border-radius:10px;font-size:13px}.settings-message.error{background:#fff0ed;color:#9a443b}.settings-message.success{background:#edf7ef;color:#326b42}.settings-password{margin-top:26px;padding-top:24px;border-top:1px solid #eee9e1}.settings-hint{margin:0 0 14px;color:#81786e;font-size:12px;line-height:1.55}.settings-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:23px}.settings-logout{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 15px;border:1px solid #e9ddd9;border-radius:10px;color:#9a5148;text-decoration:none;font-size:13px;font-weight:650}.settings-logout:hover{background:#fff5f3}.settings-back-link{display:inline-grid;place-items:center;width:44px;height:44px;border:1px solid #cfe2d2;border-radius:12px;background:#f5faf6;color:#247245;text-decoration:none;transition:background-color .16s ease,transform .16s ease}.settings-back-link:hover{background:#eaf5ed;transform:translateX(-2px)}.settings-back-link svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .profile-picture-row{display:flex;align-items:center;gap:18px;margin:0 0 23px;padding:17px;border:1px solid #e5eee6;border-radius:14px;background:#f8fbf8}.profile-picture-avatar{display:grid;place-items:center;width:78px;height:78px;flex:none;overflow:hidden;border:3px solid #fff;border-radius:50%;background:#e6f2e8;color:#34784b;font-size:20px;font-weight:800;box-shadow:0 0 0 1px #dbe8dc}.profile-picture-avatar img{width:100%;height:100%;object-fit:cover}.profile-picture-copy{min-width:0;flex:1}.profile-picture-copy strong{display:block;margin-bottom:4px;color:#304a37;font-size:14px}.profile-picture-copy p{margin:0 0 10px;color:#77857a;font-size:12px}.profile-upload-control{position:relative;display:flex;align-items:center;gap:12px;min-height:48px;max-width:100%;overflow:hidden;padding:6px 12px;border:1px solid #dce8de;border-radius:11px;background:#fff;color:#66766a;cursor:pointer;transition:border-color .16s ease,box-shadow .16s ease,background-color .16s ease}.profile-upload-control:hover{border-color:#9fc5a6;background:#fcfefc}.profile-upload-control:focus-within{border-color:#65a878;box-shadow:0 0 0 3px #19804a20}.profile-upload-action{display:inline-flex;align-items:center;gap:7px;flex:none;min-height:34px;padding:0 11px;border-radius:8px;background:#eaf5ed;color:#217344;font-size:13px;font-weight:750}.profile-upload-action svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.profile-upload-name{overflow:hidden;color:#718076;font-size:13px;text-overflow:ellipsis;white-space:nowrap}.profile-picture-input{position:absolute;inset:0;width:100%;height:100%;margin:0;padding:0;border:0;opacity:0;cursor:pointer}.profile-picture-note{display:block;margin-top:7px;color:#88958b;font-size:11px}
    @media(max-width:600px){.settings-page{padding:24px 15px}.settings-top{align-items:flex-start;flex-direction:column}.settings-top h1{font-size:25px}.settings-card{padding:19px}.settings-actions{flex-direction:column-reverse}.settings-actions>*{width:100%}}
  </style>
</head>
<body class="min-h-screen antialiased">
  <main class="settings-page">
    <header class="settings-top"><div><p class="eyebrow">MAZSEN MUNCH &amp; SIP</p><h1>Account settings</h1><p>Update the contact and delivery information used for your orders.</p></div><a class="settings-back-link" href="../index.php" aria-label="Back to store" title="Back to store"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></a></header>
    <section class="settings-card">
      <?php if ($error): ?><p class="settings-message error" role="alert"><?= settings_h($error) ?></p><?php endif; ?>
      <?php if ($saved): ?><p class="settings-message success" role="status">Your account information has been updated.</p><?php endif; ?>
      <form method="post" action="settings.php" autocomplete="on" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= settings_h($_SESSION['customer_settings_csrf']) ?>">
        <div class="profile-picture-row"><span class="profile-picture-avatar" id="profile-picture-preview"><?php if (!empty($customer['profile_image'])): ?><img src="../<?= settings_h($customer['profile_image']) ?>" alt="Customer profile picture"><?php else: ?><?= settings_h(customer_settings_initials((string)$customer['full_name'])) ?><?php endif; ?></span><div class="profile-picture-copy"><strong>Profile picture</strong><p>Show your photo beside your name on your MazSen account.</p><label class="profile-upload-control" for="profile-picture-input"><span class="profile-upload-action"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-5"/></svg>Choose photo</span><span class="profile-upload-name" id="profile-picture-filename">No file selected</span><input id="profile-picture-input" class="profile-picture-input" type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-picture-note"></label><span class="profile-picture-note" id="profile-picture-note">JPG, PNG, or WebP · Up to 2 MB</span></div></div>
        <h2 class="settings-section-title">Personal information</h2><p class="settings-hint">Edit any field below, then choose Save changes to update your account.</p>
        <div class="grid gap-4 md:grid-cols-2">
          <label class="settings-field md:col-span-2">Full name<input name="full_name" autocomplete="name" maxlength="120" value="<?= settings_h($customer['full_name']) ?>" required></label>
          <label class="settings-field">Email address<input type="email" name="email" autocomplete="email" maxlength="190" value="<?= settings_h($customer['email']) ?>" required></label>
          <label class="settings-field">Phone number<input type="tel" name="phone" autocomplete="tel" maxlength="30" value="<?= settings_h($customer['phone']) ?>" required></label>
          <label class="settings-field md:col-span-2">Complete delivery address<textarea name="address" autocomplete="street-address" maxlength="500" rows="3" required><?= settings_h($customer['address']) ?></textarea></label>
        </div>
        <div class="settings-password"><h2 class="settings-section-title">Change password</h2><p class="settings-hint">Leave these fields blank if you do not want to change your password. Your current password is required to set a new one.</p><div class="grid gap-4 md:grid-cols-2">
          <label class="settings-field md:col-span-2">Current password<input type="password" name="current_password" autocomplete="current-password"></label>
          <label class="settings-field">New password<input type="password" name="new_password" autocomplete="new-password" minlength="8"><small class="font-normal text-gray-500">At least 8 characters</small></label>
          <label class="settings-field">Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8"></label>
        </div></div>
        <div class="settings-actions"><a class="settings-logout" href="logout.php">Log out</a><button class="primary-button justify-center" type="submit">Save changes</button></div>
      </form>
    </section>
</main>
<script>
const profileInput = document.querySelector('input[name="profile_picture"]');
const profilePreview = document.getElementById('profile-picture-preview');
const profileFilename = document.getElementById('profile-picture-filename');
let profilePreviewUrl = null;
profileInput?.addEventListener('change', () => {
  const file = profileInput.files?.[0];
  profileFilename.textContent = file?.name || 'No file selected';
  if (!file || !file.type.startsWith('image/')) return;
  if (profilePreviewUrl) URL.revokeObjectURL(profilePreviewUrl);
  profilePreviewUrl = URL.createObjectURL(file);
  profilePreview.replaceChildren(Object.assign(document.createElement('img'), { src: profilePreviewUrl, alt: 'Selected profile picture preview' }));
});
</script>
<script src="../assets/js/logout-transition.js?v=1"></script>
</body>
</html>
