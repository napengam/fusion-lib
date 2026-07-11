<?php

class Auth {

    private PDODB $db;

    public function __construct(PDODB $db) {
        $this->db = $db;

    }

    /* =========================
      LOGIN
      ========================= */

    public function login(string $username, string $password): array {

        $ip = $this->getClientIp();

        $userAttempt = $this->getAttempts('username', $username);
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
                "SELECT * FROM users WHERE username = ? LIMIT 1",
                [$username]
        );
        if (!$user || !sodium_crypto_pwhash_str_verify($user->password, $password)) {
            $this->increaseAttempts('username', $username, $userAttempt['attempts'] + 1);
            $this->increaseAttempts('ip', $ip, $ipAttempt['attempts'] + 1);
            return $this->result(false, 'invalid_credentials');
        }

        $this->clearAttempts('username', $username);
        $this->clearAttempts('ip', $ip);
        
        unset($user->password);

        return $this->result(true, $user);
    }

   
    /* =========================
      REGISTER
      ========================= */

    public function register(string $username, string $password, string $email): array {

        $username = trim($username);

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
                "SELECT id FROM users WHERE username = ? LIMIT 1",
                [$username]
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
            'username' => $username, // 
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
            'attempts' => (int) $row->attempts,
            'time' => (int) $row->last_attempt
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
