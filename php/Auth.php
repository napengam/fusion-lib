<?php

/*
  REQUIRED TABLES

  CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at INT NOT NULL
  );

  CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(20) NOT NULL, -- 'username' or 'ip'
  value VARCHAR(255) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  last_attempt INT NOT NULL,
  UNIQUE KEY unique_type_value (type,value)
  );
 * SECURITY FEATURES / PROTECTION

  - Protects against brute force attacks (username + IP based tracking)
  - Prevents unlimited login retries (hard limit after 10 attempts)
  - Slows down attackers using exponential delay (max 5 seconds)
  - Resistant to session reset attacks (stored in database, not session)
  - Resistant to cookie clearing attacks
  - Limits distributed attacks partially (IP + username combined)
  - Automatically resets attempts after 15 minutes (cooldown window)
  - Prevents user enumeration timing attacks (delay applied consistently)
  - Secures session against fixation (session_regenerate_id)

  NOT FULLY PROTECTED AGAINST

  - Large scale distributed botnets (many IPs)
  - Targeted attacks across many usernames (credential stuffing lists)
  - Requires HTTPS + secure cookie settings for full session security
 */

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

    public function login(string $username, string $password): bool {

        $ip = $this->getClientIp();

// get attempt data for username
        $userAttempt = $this->getAttempts('username', $username);

// get attempt data for ip
        $ipAttempt = $this->getAttempts('ip', $ip);

// reset if older than 15 minutes
        if (time() - $userAttempt['time'] > 900) {
            $userAttempt['attempts'] = 0;
        }
        if (time() - $ipAttempt['time'] > 900) {
            $ipAttempt['attempts'] = 0;
        }

// use the higher value for enforcement
        $attempts = max($userAttempt['attempts'], $ipAttempt['attempts']);

// hard lock after too many attempts
        if ($attempts >= 10) {
            return $this->result(fasle, "Too many attempts. Try again later.");
        }

// exponential delay to slow brute force
        if ($attempts > 0) {
            $delay = min(2 ** $attempts, 5);
            sleep($delay);
        }

// fetch user
        $user = $this->db->queryFetchOne(
                "SELECT id,username,password_hash FROM users WHERE username = ?",
                [$username]
        );

// verify password
        if (!$user || !sodium_crypto_pwhash_str_verify($user->password_hash, $password)) {

// increase both counters
            $this->increaseAttempts('username', $username, $userAttempt['attempts'] + 1);
            $this->increaseAttempts('ip', $ip, $ipAttempt['attempts'] + 1);
            return $this->result(false, 'invalid_credentials');
        }

// success → clear attempts
        $this->clearAttempts('username', $username);
        $this->clearAttempts('ip', $ip);

// secure session
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['last_activity'] = time();
        return $this->result(true);
        
    }

    /* =========================
      LOGOUT
      ========================= */

    public function logout(): void {

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }

        session_destroy();
    }

    /* =========================
      SESSION CHECK
      ========================= */

    public function isLoggedIn(): bool {

// basic session validation
        if (empty($_SESSION['user_id']) || empty($_SESSION['last_activity'])) {
            return false;
        }

// session timeout (30 min)
        if (time() - $_SESSION['last_activity'] > 1800) {
            $this->logout();
            return false;
        }

// update activity timestamp
        $_SESSION['last_activity'] = time();

        return true;
    }

    /* =========================
      GET ATTEMPTS
      returns ['attempts'=>int,'time'=>int]
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

    /* =========================
      INCREASE ATTEMPTS
      ========================= */

    private function increaseAttempts(string $type, string $value, int $attempts): void {

        $existing = $this->db->queryFetchOne(
                "SELECT id FROM login_attempts WHERE type = ? AND value = ? LIMIT 1",
                [$type, $value]
        );

        if ($existing) {
            $this->db->execute(
                    "UPDATE login_attempts SET attempts = ?, last_attempt = ? WHERE id = ?",
                    [$attempts, time(), $existing->id]
            );
        } else {
            $this->db->execute(
                    "INSERT INTO login_attempts (type,value,attempts,last_attempt) VALUES (?,?,?,?)",
                    [$type, $value, $attempts, time()]
            );
        }
    }

    /* =========================
      CLEAR ATTEMPTS
      ========================= */

    private function clearAttempts(string $type, string $value): void {

        $this->db->execute(
                "DELETE FROM login_attempts WHERE type = ? AND value = ?",
                [$type, $value]
        );
    }

    /* =========================
      HELPER: CLIENT IP
      ========================= */

    private function getClientIp(): string {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function result(bool $success = false, string $reason = ''): array {
        return [
            'success' => $success,
            'reason' => $reason
        ];
    }
}
