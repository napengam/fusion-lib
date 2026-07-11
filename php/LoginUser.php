<?php

/**
 * LoginUser handles session-based authentication flow and access control.
 *
 * Responsibilities:
 * - Initializes and hardens PHP sessions (secure cookies, HttpOnly, SameSite)
 * - Enforces session inactivity timeout with automatic logout
 * - Processes login requests using the Auth class for credential verification
 * - Regenerates session IDs on login to prevent session fixation
 * - Stores authenticated user data securely in the session
 * - Protects POST requests with CSRF token validation
 * - Supports public route whitelisting (no authentication required)
 * - Provides secure logout with full session destruction
 *
 * Notes:
 * - This class is designed for server-rendered or form-based login flows
 * - It expects credentials via POST (username/password)
 * - Auth class is responsible for actual password verification and user lookup
 * - Session state is the single source of truth for authentication
 *
 * Security Features:
 * - Session fixation protection via session_regenerate_id()
 * - Secure cookie flags (Secure, HttpOnly, SameSite=Strict)
 * - CSRF protection for login requests
 * - Automatic session expiration based on inactivity
 *
 * Typical Flow:
 * 1. Constructor initializes session and checks public routes
 * 2. Session timeout is enforced
 * 3. If POST request → credentials are validated via Auth
 * 4. On success → session is populated and user is authenticated
 * 5. On inactivity timeout → session is destroyed and user is logged out
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
            return $result;
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
