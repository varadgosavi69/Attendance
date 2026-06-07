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
        // Only allow principal role through this endpoint
        if ($user['role'] !== 'principal') {
            // Destroy session — wrong portal
            $auth->logout();
            echo json_encode(['success' => false, 'message' => 'Access denied. Faculty must use the Faculty Portal.']);
            exit;
        }
        echo json_encode(['success' => true, 'redirect' => 'principal_dashboard.php']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>
