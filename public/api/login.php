<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

try {
    $auth = new Auth();

    if ($auth->login($username, $password)) {
        $user = $auth->getUser();
        $role = $user['role'];

        // Block principal from using the faculty portal
        if ($role === 'principal') {
            $auth->logout();
            echo json_encode(['success' => false, 'message' => 'Please use the Principal Portal to login.']);
            exit;
        }

        // Role-based redirect
        $redirect = match($role) {
            'hod'   => 'hod_dashboard.php',
            default => 'dashboard.php',
        };

        echo json_encode(['success' => true, 'redirect' => $redirect]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>