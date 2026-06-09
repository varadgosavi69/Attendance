<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/DetentionProcessor.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$month = $_GET['month'] ?? date('Y-m', strtotime('first day of last month'));

// Parse "YYYY-MM" format
$parts = explode('-', $month);
if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid month format. Use YYYY-MM.']);
    exit;
}

$year  = (int) $parts[0];
$monthNum = (int) $parts[1];

try {
    $processor = new DetentionProcessor();
    $data      = $processor->getMonthlyAttendance($year, $monthNum);
    echo json_encode(['success' => true, 'data' => $data, 'threshold' => DETENTION_THRESHOLD]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
