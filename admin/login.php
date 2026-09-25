<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

start_secure_session();

// If already logged in, direct to dashboard
if (is_admin_logged_in()) {
    header('Location: ' . app_url('/admin/index.php'));
    exit;
}

$error = '';
$settings = get_site_settings();
$brandName = $settings['brand_name'] ?? "Shelly's Jellys LLC";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Rate limiting check
    if (!check_rate_limit('admin_login', 6, 300)) {
        $error = 'Too many login attempts. Please wait 5 minutes before trying again.';
    } else {
        // 2. CSRF Token verification
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            $error = 'Session security token expired. Please refresh the page.';
        } else {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if (empty($username) || empty($password)) {
                $error = 'Please enter both username and password.';
            } else {
                $db = get_db();
                $stmt = $db->prepare("SELECT id, username, password_hash, email FROM admin_users WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Password matches bcrypt hash
                    session_regenerate_id(true);
                    $_SESSION['admin_user_id'] = (int)$user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_email'] = $user['email'];

                    // Update last_login timestamp
                    try {
                        $upd = $db->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                        $upd->execute([$user['id']]);
                    } catch (Exception $e) {
                        // ignore
                    }

                    header('Location: ' . app_url('/admin/index.php'));
                    exit;
                } else {
                    $error = 'Invalid administrative credentials.';
                }
            }
        }
    }
}

$csrfToken = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Sign In | <?= htmlspecialchars($brandName) ?></title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/css/admin.css'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(app_url('/public/assets/images/logo.svg'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
</head>
<body class="admin-body">

<div class="admin-login-wrapper">
  <div class="admin-login-card">
    <div class="admin-login-header">
      <div style="font-size:2.8rem; margin-bottom:0.5rem;">🍓</div>
      <h1 class="admin-login-title"><?= htmlspecialchars($brandName) ?></h1>
      <p class="admin-login-sub">Secure Kitchen & Storefront Management</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="admin-alert admin-alert-error" role="alert">
        <span>⚠️</span>
        <div><?= htmlspecialchars($error) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars(app_url('/admin/login.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

      <div class="form-group">
        <label for="username">Username</label>
        <input 
          type="text" 
          id="username" 
          name="username" 
          required 
          autocomplete="username" 
          value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
          placeholder="admin"
          autofocus
        >
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          required 
          autocomplete="current-password"
          placeholder="••••••••••••"
        >
      </div>

      <div style="margin-top: 1.5rem;">
        <button type="submit" class="btn-primary-action" style="width:100%; justify-content:center; padding:0.8rem;">
          Sign In to Dashboard &rarr;
        </button>
      </div>
    </form>

    <div style="margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid #e2e8f0; text-align:center; font-size:0.8rem; color:#64748b;">
      <div>Session-based bcrypt authentication</div>
      <div style="margin-top:0.4rem;">Default Seed: <code>admin</code> / <code>JamAdmin2026!</code></div>
      <div style="margin-top:0.85rem;">
        <a href="<?= htmlspecialchars(app_url('/public/index.html'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="color:#134e3f; text-decoration:none; font-weight:600;">&larr; Return to Storefront</a>
      </div>
    </div>
  </div>
</div>

</body>
</html>
