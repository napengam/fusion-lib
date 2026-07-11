<?php

class Auth {

    private PDODB $db;

    public function __construct(PDODB $db) {
        $this->db = $db;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new Exception('[400] Session not started.');
        }
    }

    /* =========================
      LOGIN
      ========================= */

    public function login(string $username, string $password): array {

        $ip = $this->getClientIp();
        $normalized = $this->normalizeUsername($username);

        $userAttempt = $this->getAttempts('username', $normalized);
        $ipAttempt = $this->getAttempts('ip', $ip);

        if (time() - $userAttempt['time'] > 900) {
            $userAttempt['attempts'] = 0;
        }
        if (time() - $ipAttempt['time'] > 900) {
            $ipAttempt['attempts'] = 0;
        }

        $attempts = max($userAttempt['attempts'], $ipAttempt['attempts']);

        if ($attempts >= 10) {
            return $this->result(false, "Too many attempts. Try again later.");
        }

        if ($attempts > 0) {
            $delay = min(2 ** $attempts, 5);
            sleep($delay);
        }

        $user = $this->db->queryFetchOne(
            "SELECT * FROM users WHERE LOWER(username) = ? LIMIT 1",
            [$normalized]
        );

        if (!$user || !sodium_crypto_pwhash_str_verify($user->password, $password)) {
            $this->increaseAttempts('username', $normalized, $userAttempt['attempts'] + 1);
            $this->increaseAttempts('ip', $ip, $ipAttempt['attempts'] + 1);
            return $this->result(false, 'invalid_credentials');
        }

        $this->clearAttempts('username', $normalized);
        $this->clearAttempts('ip', $ip);

        // ✅ session setup
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->id;
        $_SESSION['last_activity'] = time();

        unset($user->password);

        return $this->result(true, $user);
    }

    /* =========================
      LOGOUT
      ========================= */

    public function logout(): void {

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }

    /* =========================
      REGISTER
      ========================= */

    public function register(string $username, string $password, string $email): array {

        $username = trim($username);
        $normalized = $this->normalizeUsername($username);

        if ($username === '' || $password === '') {
            return $this->result(false, 'empty_fields');
        }

        if (strlen($username) < 3 || strlen($username) > 50) {
            return $this->result(false, 'invalid_username_length');
        }

        if (strlen($password) < 6) {
            return $this->result(false, 'password_too_short');
        }

        $existing = $this->db->queryFetchOne(
            "SELECT id FROM users WHERE LOWER(username) = ? LIMIT 1",
            [$normalized]
        );

        if ($existing) {
            // timing protection
            sodium_crypto_pwhash_str(
                $password,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
            );
            return $this->result(false, 'user_exists');
        }

        $hash = sodium_crypto_pwhash_str(
            $password,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );

        $id = $this->db->insert('users', [
            'username' => $username, // ✅ keep original
            'password' => $hash,
            'email' => $email,
            'created' => time()
        ]);

        $user = $this->db->queryFetchOne(
            "SELECT * FROM users WHERE id = ? LIMIT 1",
            [$id]
        );

        unset($user->password);

        return $this->result(true, $user);
    }

    /* =========================
      SESSION CHECK
      ========================= */

    public function isLoggedIn(): bool {

        if (empty($_SESSION['user_id']) || empty($_SESSION['last_activity'])) {
            return false;
        }

        if (time() - $_SESSION['last_activity'] > 1800) {
            $this->logout();
            return false;
        }

        $_SESSION['last_activity'] = time();

        return true;
    }

    /* =========================
      ATTEMPTS
      ========================= */

    private function getAttempts(string $type, string $value): array {

        $row = $this->db->queryFetchOne(
            "SELECT attempts,last_attempt FROM login_attempts WHERE type = ? AND value = ? LIMIT 1",
            [$type, $value]
        );

        if (!$row) {
            return ['attempts' => 0, 'time' => 0];
        }

        return [
            'attempts' => (int)$row->attempts,
            'time' => (int)$row->last_attempt
        ];
    }

    private function increaseAttempts(string $type, string $value, int $attempts): void {

        $existing = $this->db->queryFetchOne(
            "SELECT id FROM login_attempts WHERE type = ? AND value = ? LIMIT 1",
            [$type, $value]
        );

        if ($existing) {
            $this->db->query(
                "UPDATE login_attempts SET attempts = ?, last_attempt = ? WHERE id = ?",
                [$attempts, time(), $existing->id]
            );
        } else {
            $this->db->query(
                "INSERT INTO login_attempts (type,value,attempts,last_attempt) VALUES (?,?,?,?)",
                [$type, $value, $attempts, time()]
            );
        }
    }

    private function clearAttempts(string $type, string $value): void {

        $this->db->query(
            "DELETE FROM login_attempts WHERE type = ? AND value = ?",
            [$type, $value]
        );
    }

    /* =========================
      HELPERS
      ========================= */

    private function normalizeUsername(string $username): string {
        return strtolower(trim($username));
    }

    private function getClientIp(): string {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function result(bool $success = false, mixed $reason = ''): array {
        return [
            'success' => $success,
            'reason' => $reason
        ];
    }
}