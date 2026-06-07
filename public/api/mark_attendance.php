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

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['subject_id'], $data['date'], $data['records']) || !is_array($data['records'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
    exit;
}

try {
    $db   = Database::getInstance()->getConnection();
    $user = $auth->getUser();

    // --- Faculty-subject ownership check ---
    // If the logged-in user is linked to a faculty, ensure they own this subject.
    if (!empty($user['faculty_id'])) {
        $ownerStmt = $db->prepare("
            SELECT COUNT(*) FROM faculty_subjects
            WHERE faculty_id = :faculty_id AND subject_id = :subject_id
        ");
        $ownerStmt->execute([
            'faculty_id' => $user['faculty_id'],
            'subject_id' => $data['subject_id'],
        ]);
        if ((int)$ownerStmt->fetchColumn() === 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are not authorized to mark attendance for this subject.']);
            exit;
        }
        $facultyId = $user['faculty_id'];
    } else {
        // Admin: look up by full_name as before (graceful fallback for admins)
        $facultyStmt = $db->prepare("SELECT faculty_id FROM faculty WHERE faculty_name = :name LIMIT 1");
        $facultyStmt->execute(['name' => $user['full_name']]);
        $faculty   = $facultyStmt->fetch();
        $facultyId = $faculty ? $faculty['faculty_id'] : null;
    }

    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO attendance (student_id, subject_id, faculty_id, attendance_date, status) 
        VALUES (:student_id, :subject_id, :faculty_id, :date, :status)
        ON DUPLICATE KEY UPDATE status = VALUES(status), marked_at = CURRENT_TIMESTAMP
    ");

    foreach ($data['records'] as $studentId => $status) {
        $stmt->execute([
            'student_id' => $studentId,
            'subject_id' => $data['subject_id'],
            'faculty_id' => $facultyId,
            'date'       => $data['date'],
            'status'     => $status,
        ]);
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>