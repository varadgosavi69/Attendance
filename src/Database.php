<?php
require_once __DIR__ . '/../config/config.php';

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 2, // 2 seconds timeout
        ];

        try {
            error_log("Database: Connecting to " . DB_HOST . ":" . DB_PORT);
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            error_log("Database: SUCCESS");
        } catch (PDOException $e) {
            error_log("Database: Connection failed! - " . $e->getMessage());
            throw new Exception("Database Connection Failed: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }
}
?>