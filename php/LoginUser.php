<?php

/**
 * LoginUser class provides comprehensive authentication functionality including:
 * - Secure session management with timeout enforcement
 * - Login/logout flows with CSRF protection
 * - Password verification with legacy MD5 migration support
 * - Role-based permission checking
 * - Secure session initialization and hardening
 * - Configurable authentication table/field mappings
 * - Automatic password hash upgrading
 * - Public route whitelisting
 */
class LoginUser {

    private PDO $db;
    private array $authConfig;
    private int $sessionTimeout; // inactivity limit in seconds

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
        // Allow public (unauthenticated) routes
        if ($this->allowPublicRoutes([
                    '/forgot.php', '/forgotSend.php', '/forgotNew.php', '/forgotSave.php'
                ])) {
            return;
        }
        $this->secureSessionInit();
        $this->enforceSessionTimeout();
        $this->login();
    }

    /* ---------------- PUBLIC ROUTE CHECK ---------------- */

    private function allowPublicRoutes(array $allowed): bool {
        $current = $_SERVER['PHP_SELF'] ?? '';
        foreach ($allowed as $route) {
            if (str_ends_with($current, $route)) {
                return true;
            }
        }
        return false;
    }

    /* ---------------- SESSION HARDENING ---------------- */

    private function secureSessionInit(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            session_start();
        }
    }

    /* ---------------- SESSION TIMEOUT ---------------- */

    private function enforceSessionTimeout(): void {
        if (!empty($_SESSION['last_activity'])) {
            $elapsed = time() - $_SESSION['last_activity'];

            if ($elapsed > $this->sessionTimeout) {
                $this->logout('Session expired. Please log in again.');
            }
        }

        $_SESSION['last_activity'] = time();
    }

    /* ---------------- LOGIN FLOW ---------------- */

    private function login(): array {
        global $logged_in;

        if (!empty($_SESSION['user_id'])) {
            $logged_in = true;
            return ["success" => true, 'reason' => 'logged'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $this->verifyCsrf();

            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $auth = new Auth($this->db);
            $result = $auth->login($username, $password);
            if (!$result['success']) {
                return $result;
            }

            session_regenerate_id(true);

            $user = $result['reason'];
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $username;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['last_activity'] = time();

            if (!empty($this->authConfig['role_field'])) {
                $_SESSION['role'] = $this->getRole($username);
            }

            $logged_in = true;
        }
    }

    public static function logout(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Clear session array
            $_SESSION = [];

            // Destroy session cookie
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                        session_name(),
                        '',
                        time() - 42000,
                        $params['path'],
                        $params['domain'],
                        $params['secure'],
                        $params['httponly']
                );
            }

            // Fully destroy session
            session_destroy();
        }
        // Optionally redirect to login or home
        header('Location: /login.php');
        exit;
    }

    /* ---------------- CSRF ---------------- */

    private function verifyCsrf(): void {
        if (
                empty($_POST['csrf']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])
        ) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }
    }
}
