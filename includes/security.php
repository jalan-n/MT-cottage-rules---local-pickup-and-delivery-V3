<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Security, Authentication, Rate Limiting & Defense Infrastructure
 */

/**
 * Start session with hardened cookie parameters
 */
function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }
}

/**
 * Generate or retrieve CSRF token
 */
function get_csrf_token(): string {
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token against session
 */
function verify_csrf_token(?string $token): bool {
    start_secure_session();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token on state-changing requests or abort with 403
 */
function require_csrf_token(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            echo json_encode(['success' => false, 'error' => 'Security token invalid or expired. Please refresh and try again.']);
        } else {
            echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem;text-align:center;"><h2>403 Forbidden</h2><p>Invalid security token. <a href="javascript:history.back()">Return</a></p></body></html>';
        }
        exit;
    }
}

/**
 * Resolve the application base path for local XAMPP and hosted root/subfolder installs.
 */
function app_base_path(): string {
    $configured = getenv('APP_BASE_PATH') ?: getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food';
    $configured = trim((string)$configured);
    $configured = rtrim($configured, '/');

    return $configured === '' ? '' : $configured;
}

function app_url(string $path = ''): string {
    $base = app_base_path();
    $path = '/' . ltrim($path, '/');
    return $base === '' ? $path : $base . $path;
}

/**
 * Enforce Admin Authentication
 */
function require_admin_auth(): array {
    start_secure_session();
    if (empty($_SESSION['admin_user_id'])) {
        header('Location: ' . app_url('/admin/login.php'));
        exit;
    }

    return [
        'id'       => $_SESSION['admin_user_id'],
        'username' => $_SESSION['admin_username'] ?? 'admin',
        'email'    => $_SESSION['admin_email'] ?? ''
    ];
}

/**
 * Check if admin is currently logged in
 */
function is_admin_logged_in(): bool {
    start_secure_session();
    return !empty($_SESSION['admin_user_id']);
}

/**
 * IP-based rate limiting
 * Prevents automated brute force, spamming, and denial-of-service
 */
function check_rate_limit(string $action, int $maxAttempts = 15, int $windowSeconds = 60): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    // Normalize IP
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
    }

    $dir = dirname(__DIR__) . '/data/rate_limits';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $hash = md5($ip . '_' . $action);
    $file = $dir . '/' . $hash . '.json';
    $now = time();

    $data = ['count' => 0, 'reset' => $now + $windowSeconds];
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        $parsed = json_decode((string)$content, true);
        if (is_array($parsed) && isset($parsed['reset']) && $parsed['reset'] > $now) {
            $data = $parsed;
        }
    }

    $data['count']++;
    @file_put_contents($file, json_encode($data), LOCK_EX);

    return $data['count'] <= $maxAttempts;
}

/**
 * Sanitized file uploader with MIME inspection, size limits, and safe renaming
 */
function sanitize_and_store_upload(
    array $file, 
    string $targetDir, 
    array $allowedMimes, 
    int $maxBytes = 5242880 // 5MB default
): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file parameters.'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'No file was uploaded.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'File exceeds maximum upload size.'];
        default:
            return ['success' => false, 'error' => 'Unknown upload error.'];
    }

    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'error' => 'File exceeds the ' . round($maxBytes / 1048576, 1) . 'MB limit.'];
    }

    // Inspect real MIME type using FileInfo. Some Windows/XAMPP setups report
    // generic octet-stream for valid uploads, so we fall back to the file extension.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo !== false ? $finfo->file($file['tmp_name']) : false;
    $mime = is_string($mime) ? $mime : false;

    if ($mime === false || $mime === 'application/octet-stream' || !in_array($mime, $allowedMimes, true)) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeByExtension = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
        ];
        $mime = $mimeByExtension[$ext] ?? $mime;
    }

    if (!is_string($mime) || !in_array($mime, $allowedMimes, true)) {
        return ['success' => false, 'error' => 'Disallowed file type: ' . ($mime ?: 'unknown') . '.'];
    }

    // Determine safe extension based on real MIME
    $extensions = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/svg+xml'   => 'svg',
        'application/pdf' => 'pdf'
    ];

    $ext = $extensions[$mime] ?? pathinfo($file['name'], PATHINFO_EXTENSION);
    $baseName = pathinfo($file['name'], PATHINFO_FILENAME);
    $cleanName = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower($baseName));
    if (empty($cleanName)) {
        $cleanName = 'asset';
    }

    $uniqueName = $cleanName . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destination = rtrim($targetDir, '/') . '/' . $uniqueName;

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $moved = move_uploaded_file($file['tmp_name'], $destination);
    if (!$moved && file_exists($file['tmp_name'])) {
        $moved = copy($file['tmp_name'], $destination);
    }

    if (!$moved) {
        return ['success' => false, 'error' => 'Failed to move uploaded file to destination.'];
    }

    return [
        'success'   => true,
        'filename'  => $uniqueName,
        'mime'      => $mime,
        'size'      => $file['size'],
        'filepath'  => $destination
    ];
}

/**
 * Strict XSS Escaping Helper
 */
function e(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
