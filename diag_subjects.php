<?php
require_once __DIR__ . '/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__))->safeLoad();

$db = new PDO(
    'mysql:host='.$_ENV['DB_HOST'].';port='.$_ENV['DB_PORT'].';dbname='.$_ENV['DB_NAME'].';charset=utf8mb4',
    $_ENV['DB_USER'], $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "=== SUBJECTS TABLE ===\n";
$rows = $db->query("SELECT subject_id, subject_code, subject_name, department, semester FROM subjects ORDER BY department, semester")->fetchAll();
if (empty($rows)) {
    echo "[EMPTY] No subjects found!\n";
} else {
    foreach ($rows as $r) {
        echo "ID:{$r['subject_id']} | {$r['subject_code']} | {$r['subject_name']} | {$r['department']} | Sem{$r['semester']}\n";
    }
}

echo "\n=== FACULTY_SUBJECTS LINKS ===\n";
$links = $db->query("SELECT fs.faculty_id, fs.subject_id, u.username, f.name as faculty_name FROM faculty_subjects fs LEFT JOIN faculty f ON f.faculty_id = fs.faculty_id LEFT JOIN users u ON u.faculty_id = fs.faculty_id")->fetchAll();
if (empty($links)) {
    echo "[EMPTY] No faculty-subject links! Admin user will see ALL subjects.\n";
} else {
    foreach ($links as $r) {
        echo "Faculty:{$r['faculty_id']} ({$r['faculty_name']}) -> Subject:{$r['subject_id']} (user:{$r['username']})\n";
    }
}

echo "\n=== USERS TABLE (roles) ===\n";
$users = $db->query("SELECT user_id, username, role, faculty_id, department FROM users")->fetchAll();
foreach ($users as $u) {
    echo "ID:{$u['user_id']} | {$u['username']} | role:{$u['role']} | faculty_id:".($u['faculty_id'] ?? 'null')." | dept:".($u['department'] ?? 'null')."\n";
}

echo "\n=== get_subjects.php TEST (as admin) ===\n";
// Simulate what get_subjects.php does for admin (no faculty_id)
$stmt = $db->query("SELECT subject_id, subject_name, subject_code, department, semester FROM subjects ORDER BY subject_name ASC");
$subjects = $stmt->fetchAll();
echo "Would return ".count($subjects)." subjects for admin user\n";
echo json_encode(['success' => true, 'data' => $subjects], JSON_PRETTY_PRINT) . "\n";
