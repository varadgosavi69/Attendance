<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db   = Database::getInstance()->getConnection();

    // Always return all subjects — the attendance page JS filters by branch + semester client-side
    $stmt = $db->query("SELECT subject_id, subject_name, subject_code, department, semester FROM subjects ORDER BY department ASC, semester ASC, subject_name ASC");

    $subjects = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $subjects]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>