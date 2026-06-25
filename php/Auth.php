<?php

/**
 * Auth class handles user authentication including registration, login, logout,
 * session management, and secure password storage using libsodium.
 * Provides methods to check authentication status and retrieve user session data.
 * It makes uese of class PDODB;
 */
class Auth {

    private $db;
    private $encryption;

    public function __construct(PDODB $db) {
        $this->db = $db;
        $this->encryption = new SecureEncryption(APP_KEY);
    }

    public function register(string $username, string $password): bool {
        if (strlen($password) < 1) {
            // this is on you; I except evry Lengt >0
            throw new Exception("Password must be at least ? characters long.");
        }

        // Check if username exists
        $existing = $this->db->queryFetchOne(
                "SELECT id FROM users WHERE username = ?",
                [$username]
        );

        if ($existing) {
            throw new Exception("Username already exists.");
        }

        $salt = random_bytes(16);
        $passwordHash = sodium_crypto_pwhash_str(
                $password,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );

        try {
            $this->db->begin();
            $this->db->query(
                    "INSERT INTO users (username, password_hash, encryption_salt) VALUES (?, ?, ?)",
                    [$username, $passwordHash, $salt]
            );
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function login(string $username, string $password): bool {
        $user = $this->db->queryFetchOne(
                "SELECT id, password_hash, encryption_salt FROM users WHERE username = ?",
                [$username]
        );

        if (!$user || !sodium_crypto_pwhash_str_verify($user->password_hash, $password)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $username;
        $_SESSION['encryption_salt'] = $user->encryption_salt;
        $_SESSION['last_activity'] = time();

        return true;
    }

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

    public function isLoggedIn(): bool {
        if (empty($_SESSION['user_id']) || empty($_SESSION['last_activity'])) {
            return false;
        }

        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    public function getUserId(): ?int {
        return $this->isLoggedIn() ? (int) $_SESSION['user_id'] : null;
    }

    public function getEncryptionSalt(): ?string {
        return $this->isLoggedIn() ? $_SESSION['encryption_salt'] : null;
    }
}
