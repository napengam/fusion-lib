<?php

class LoginUser {

    private PDO $db;
    private array $authConfig;
    private int $sessionTimeout; // inactivity limit in seconds

    public function __construct(PDO $pdo, array $authConfig = [], int $timeoutSeconds = 900) {
        $this->db = $pdo;
        $this->sessionTimeout = $timeoutSeconds;

        // Default table/column config
        $defaults = [
            'table' => 'users',
            'id_field' => 'id',
            'username_field' => 'username',
            'password_field' => 'password',
            'role_field' => null,
            'active_field' => null
        ];

        $this->authConfig = array_merge($defaults, $authConfig);

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

    private function login(): void {
        global $logged_in;

        if (!empty($_SESSION['user_id'])) {
            $logged_in = true;
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processLogin();
        }

        echo $this->renderLoginForm();
        exit;
    }

    /* ---------------- AUTH LOGIC ---------------- */

    private function processLogin(): void {
        global $logged_in, $config;
        $this->verifyCsrf();

        $uField = $this->authConfig['username_field'];
        $pField = $this->authConfig['password_field'];
        $idField = $this->authConfig['id_field'];
        $table = $this->authConfig['table'];
        $active = $this->authConfig['active_field'];

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $sql = "SELECT $idField AS id, $pField AS password"
                . ($active ? ", $active AS active" : "")
                . " FROM $table WHERE $uField = ? LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$user) {
            http_response_code(403);
            echo $this->renderLoginForm();
            exit;
        }

        if ($active && !$user->active) {
            http_response_code(403);
            echo $this->renderLoginForm();
            exit;
        }

        if (!$this->verifyPassword($password, $user)) {
            http_response_code(403);
            echo $this->renderLoginForm();
            exit;
        }

        // Successful login
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $username;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['last_activity'] = time();

        if (!empty($this->authConfig['role_field'])) {
            $_SESSION['role'] = $this->getRole($username);
        }

        $logged_in = true;
        $redirect = $config['project']['url'] ?? '/';
        header("Location: $redirect");
        exit;
    }

    private function getRole(string $username): ?string {
        $table = $this->authConfig['table'];
        $uField = $this->authConfig['username_field'];
        $rField = $this->authConfig['role_field'];

        if (!$rField) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT $rField FROM $table WHERE $uField = ?");
        $stmt->execute([$username]);
        return $stmt->fetchColumn();
    }

    /* ---------------- PASSWORD HANDLING ---------------- */

    private function verifyPassword(string $plain, object $user): bool {
        if (password_verify($plain, $user->password)) {
            if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
                $this->upgradePassword($user->id, $plain);
            }

            return true;
        }

        // Legacy MD5 migration
        if (strlen($user->password) === 32 && md5($plain) === $user->password) {
            $this->upgradePassword($user->id, $plain);
            return true;
        }

        return false;
    }

    private function upgradePassword(int $userId, string $plain): void {
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        $table = $this->authConfig['table'];
        $idField = $this->authConfig['id_field'];
        $pField = $this->authConfig['password_field'];

        $sql = "UPDATE $table SET $pField = ? WHERE $idField = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hash, $userId]);
    }

    /* ---------------- LOGOUT ---------------- */

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

    public function hasPermission(string $permissionKey): bool {
        // 1️⃣ Must be logged in
        if (empty($_SESSION['role'])) {
            return false;
        }

        $role = $_SESSION['role'];

        // 2️⃣ Load cache if not already in session
        if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
            $_SESSION['permissions'] = $this->loadPermissionsForRole($role);
        }

        // 3️⃣ Check against cached permissions
        return in_array($permissionKey, $_SESSION['permissions'], true);
    }

    private function loadPermissionsForRole(string $role): array {
        $sql = "SELECT p.permission_key
            FROM roles r
            JOIN role_permissions rp ON rp.role_id = r.role_id
            JOIN permissions p ON p.permission_id = rp.permission_id
            WHERE r.role_name = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$role]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /* ---------------- VIEW ---------------- */

    private function renderLoginForm(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);

        ob_start();
        ?>
        <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link rel="stylesheet" href="/bulma/css/bulma.min.css">
                <title>Login</title>
            </head>
            <body>
                <div class="container" style="margin-top:3em;">
                    <form method="post" class="box" style="max-width:400px;margin:auto">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <div class="field">
                            <label class="label">Username</label>
                            <input class="input" name="username" required>
                        </div>
                        <div class="field">
                            <label class="label">Password</label>
                            <input class="input" type="password" name="password" required>
                        </div>
                        <button class="button is-primary is-fullwidth">Login</button>
                    </form>
                </div>
            </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
