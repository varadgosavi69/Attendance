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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['student_csv'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['student_csv']['tmp_name'];
$handle = fopen($file, "r");

if (!$handle) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to open file']);
    exit;
}

// Skip header row
$header = fgetcsv($handle);

$db = Database::getInstance()->getConnection();
$db->beginTransaction();

$successCount = 0;
$errorCount = 0;

try {
    $stmt = $db->prepare("
        INSERT INTO students (roll_number, student_name, email, department, semester)
        VALUES (:roll, :name, :email, :branch, :sem)
        ON DUPLICATE KEY UPDATE 
            student_name = VALUES(student_name),
            email = VALUES(email),
            department = VALUES(department),
            semester = VALUES(semester)
    ");

    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < 5) {
            $errorCount++;
            continue;
        }

        try {
            $stmt->execute([
                'roll' => trim($data[0]),
                'name' => trim($data[1]),
                'email' => trim($data[2]),
                'branch' => trim($data[3]),
                'sem' => (int) trim($data[4])
            ]);
            $successCount++;
        } catch (Exception $e) {
            $errorCount++;
        }
    }

    $db->commit();
    fclose($handle);

    echo json_encode([
        'success' => true,
        'message' => "Successfully processed students",
        'added' => $successCount,
        'errors' => $errorCount
    ]);

} catch (Exception $e) {
    $db->rollBack();
    fclose($handle);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>