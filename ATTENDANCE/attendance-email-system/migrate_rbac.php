<?php
require_once __DIR__ . '/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__))->safeLoad();

$pdo = new PDO(
    'mysql:host=' . $_ENV['DB_HOST'] . ';port=' . $_ENV['DB_PORT'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
    $_ENV['DB_USER'], $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== HOD + Principal Migration ===\n\n";

// 1. Add department column to users if not exists
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN department VARCHAR(50) NULL DEFAULT NULL AFTER faculty_id");
    echo "[OK] Added 'department' column to users table\n";
} catch (PDOException $e) {
    echo "[SKIP] department column: " . $e->getMessage() . "\n";
}

// 2. Create hod_attendance_summary table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS hod_attendance_summary (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        department      VARCHAR(50)    NOT NULL,
        semester        INT            NOT NULL,
        year            INT            NOT NULL,
        date            DATE           NOT NULL,
        total_students  INT            NOT NULL DEFAULT 0,
        present_count   INT            NOT NULL DEFAULT 0,
        attendance_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        uploaded_by     INT            NOT NULL,
        uploaded_at     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_dept_sem_date (department, semester, date),
        FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE
    )
");
echo "[OK] hod_attendance_summary table ready\n";

// 3. Seed users with correct PHP password hashes
$principalHash = password_hash('principal123', PASSWORD_DEFAULT);
$hodHash       = password_hash('hod123', PASSWORD_DEFAULT);

$users = [
    ['principal', $principalHash, 'principal@jdcoem.ac.in', 'Dr. R.S. Pande',    'principal', null],
    ['hod_cse',   $hodHash,       'hod.cse@jdcoem.ac.in',  'Dr. Imran Sheikh',   'hod',       'CSE'],
    ['hod_me',    $hodHash,       'hod.me@jdcoem.ac.in',   'Dr. Priya Sharma',   'hod',       'ME'],
    ['hod_ee',    $hodHash,       'hod.ee@jdcoem.ac.in',   'Dr. Rajesh Kumar',   'hod',       'EE'],
];

$stmt = $pdo->prepare("
    INSERT INTO users (username, password_hash, email, full_name, role, department)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        password_hash = VALUES(password_hash),
        email         = VALUES(email),
        full_name     = VALUES(full_name),
        role          = VALUES(role),
        department    = VALUES(department)
");

foreach ($users as $u) {
    $stmt->execute($u);
    echo "[OK] Upserted user: {$u[0]} (role: {$u[4]})\n";
}

echo "\n=== Migration Complete! ===\n";
echo "Credentials:\n";
echo "  Principal: username=principal  password=principal123  → http://localhost:8080/principal_login.php\n";
echo "  HOD CSE:   username=hod_cse    password=hod123        → http://localhost:8080/index.php\n";
echo "  HOD ME:    username=hod_me     password=hod123        → http://localhost:8080/index.php\n";
echo "  HOD EE:    username=hod_ee     password=hod123        → http://localhost:8080/index.php\n";
echo "  Admin:     username=admin      password=password123   → http://localhost:8080/index.php\n";
