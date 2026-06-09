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

set_time_limit(0);
ini_set('memory_limit', '512M');

$input = json_decode(file_get_contents('php://input'), true);
$year      = (int)($input['year'] ?? date('Y'));
$month     = (int)($input['month'] ?? date('m', strtotime('first day of last month')));
$sendEmails = !empty($input['send_emails']); // boolean flag

if ($year < 2020 || $year > 2100 || $month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid year or month']);
    exit;
}

try {
    $processor = new DetentionProcessor();

    // Step 1: Calculate & upsert detention records
    $students = $processor->calculateMonthlyDetention($year, $month);

    $emailResult = ['sent' => 0, 'failed' => 0];

    // Step 2: Optionally send emails to detained students
    if ($sendEmails) {
        $emailResult = $processor->sendDetentionEmails($students, $year, $month);
    }

    $detainedList = array_filter($students, fn($s) => $s['is_detained']);

    echo json_encode([
        'success'          => true,
        'message'          => 'Detention report generated.',
        'total_students'   => count($students),
        'detained_count'   => count($detainedList),
        'emails_sent'      => $emailResult['sent'],
        'emails_failed'    => $emailResult['failed'],
        'students'         => $students,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
