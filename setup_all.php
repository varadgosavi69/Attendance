<?php
/**
 * Full Database Setup Script
 * Runs: schema + detention migration + faculty_user_link migration + seed data
 */
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$host   = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port   = $_ENV['DB_PORT'] ?? '3308';
$dbname = $_ENV['DB_NAME'] ?? 'attendance_db';
$user   = $_ENV['DB_USER'] ?? 'root';
$pass   = $_ENV['DB_PASS'] ?? '';

echo "=== Attendance System - Full DB Setup ===\n";
echo "Connecting to MySQL at {$host}:{$port} ...\n";

try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "[OK] Connected to MySQL\n";

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbname}`");
    echo "[OK] Database '{$dbname}' ready\n";

    // Helper to run a SQL file
    function runSqlFile(PDO $pdo, string $path, string $label): void {
        if (!file_exists($path)) {
            echo "[WARN] File not found: {$label}\n";
            return;
        }
        $sql = file_get_contents($path);
        $sql = preg_replace('/--[^\n]*\n/', "\n", $sql); // remove line comments
        $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');
        foreach ($statements as $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                $code = $e->getCode();
                // Ignore "already exists" and "duplicate entry" errors
                if (in_array($code, ['42S01', '42000', 23000], true) || strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                    // silently skip
                } else {
                    echo "[WARN] {$label}: " . $e->getMessage() . " | SQL: " . substr($stmt, 0, 80) . "\n";
                }
            }
        }
        echo "[OK] Ran: {$label}\n";
    }

    $base = __DIR__ . '/database/';
    runSqlFile($pdo, $base . 'schema_mysql.sql',              'schema_mysql.sql');
    runSqlFile($pdo, $base . 'migration_detention.sql',        'migration_detention.sql');
    runSqlFile($pdo, $base . 'migration_faculty_user_link.sql','migration_faculty_user_link.sql');
    runSqlFile($pdo, $base . 'seed_data.sql',                  'seed_data.sql');

    echo "\n=== Setup Complete! ===\n";
    echo "Login: username=admin  password=password123\n";

} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "Make sure WampServer MySQL is running on port {$port}\n";
    exit(1);
}
