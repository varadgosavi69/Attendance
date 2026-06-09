<?php
require_once __DIR__ . '/Database.php';

class Auth
{
    private $db = null;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function getDb()
    {
        if ($this->db === null) {
            $this->db = Database::getInstance()->getConnection();
        }
        return $this->db;
    }

    public function login($username, $password)
    {
        $stmt = $this->getDb()->prepare(
            "SELECT user_id, username, password_hash, full_name, role, faculty_id, department
             FROM users WHERE username = :username OR email = :email"
        );
        $stmt->execute(['username' => $username, 'email' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['faculty_id'] = $user['faculty_id'];
            $_SESSION['department'] = $user['department'];

            $updateStmt = $this->getDb()->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :id");
            $updateStmt->execute(['id' => $user['user_id']]);

            return true;
        }

        return false;
    }

    public function logout()
    {
        session_unset();
        session_destroy();
    }

    public function isAuthenticated()
    {
        return isset($_SESSION['user_id']);
    }

    public function getUser()
    {
        if ($this->isAuthenticated()) {
            return [
                'user_id'    => $_SESSION['user_id'],
                'username'   => $_SESSION['username'],
                'role'       => $_SESSION['role'],
                'full_name'  => $_SESSION['full_name'],
                'faculty_id' => $_SESSION['faculty_id'] ?? null,
                'department' => $_SESSION['department'] ?? null,
            ];
        }
        return null;
    }

    public function requireLogin()
    {
        if (!$this->isAuthenticated()) {
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }

    /**
     * Require user to have one of the specified roles.
     * Redirects appropriately if unauthenticated or wrong role.
     */
    public function requireRole(array $roles)
    {
        if (!$this->isAuthenticated()) {
            if (in_array('principal', $roles) && count($roles) === 1) {
                header('Location: ' . BASE_URL . '/principal_login.php');
            } else {
                header('Location: ' . BASE_URL . '/index.php');
            }
            exit;
        }

        $userRole = $_SESSION['role'] ?? '';
        if (!in_array($userRole, $roles)) {
            $this->redirectToHome($userRole);
            exit;
        }
    }

    /** Check if logged-in user has a specific role. */
    public function hasRole(string $role): bool
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }

    /** Redirect user to their home page based on role. */
    public function redirectToHome(string $role = '')
    {
        if ($role === '') $role = $_SESSION['role'] ?? 'teacher';
        switch ($role) {
            case 'principal':
                header('Location: ' . BASE_URL . '/principal_dashboard.php'); break;
            case 'hod':
                header('Location: ' . BASE_URL . '/hod_dashboard.php'); break;
            default:
                header('Location: ' . BASE_URL . '/dashboard.php'); break;
        }
        exit;
    }
}
?>