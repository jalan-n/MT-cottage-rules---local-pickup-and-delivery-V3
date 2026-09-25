<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2) . '/includes/functions.php';

session_start();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username and password required']);
        exit;
    }

    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Regenerate session
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];

        // Generate token for header-based API calls
        $token = bin2hex(random_bytes(32));
        $_SESSION['admin_token'] = $token;

        $db->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);

        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ]
        ]);
        exit;
    }

    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid username or credentials']);
    exit;
}

if ($method === 'DELETE') {
    // Logout
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
    exit;
}

// Check auth status
$isAuth = !empty($_SESSION['admin_user_id']);
echo json_encode(['authenticated' => $isAuth]);
