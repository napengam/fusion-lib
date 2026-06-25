<?php

class Vault {
    private $db;
    private $encryption;
    private $userId;
    private $encryptionSalt;

    public function __construct(PDODB $db, int $userId, string $encryptionSalt) {
        $this->db = $db;
        $this->userId = $userId;
        $this->encryptionSalt = $encryptionSalt;
        $this->encryption = new SecureEncryption(APP_KEY);
    }

    public function addPassword(string $title, string $username, string $password, string $notes = ''): bool {
        $key = $this->deriveEncryptionKey($this->encryptionSalt);
        $encrypted = $this->encryption->encrypt($password, $key);

        try {
            $this->db->begin();
            $this->db->query(
                "INSERT INTO vault (user_id, title, username, ciphertext, nonce, notes) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $this->userId,
                    $title,
                    $username,
                    $encrypted['ciphertext'],
                    $encrypted['nonce'],
                    $notes
                ]
            );
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function getPasswords(): array {
        $passwords = $this->db->query(
            "SELECT id, title, username, ciphertext, nonce, notes FROM vault WHERE user_id = ?",
            [$this->userId]
        );

        $key = $this->deriveEncryptionKey($this->encryptionSalt);

        foreach ($passwords as &$password) {
            try {
                $password->password = $this->encryption->decrypt(
                    $password->ciphertext,
                    $password->nonce,
                    $key
                );
            } catch (Exception $e) {
                $password->password = '[Decryption failed]';
            }
        }

        return $passwords;
    }

    public function getPasswordById(int $id): ?object {
        $password = $this->db->queryFetchOne(
            "SELECT id, title, username, ciphertext, nonce, notes FROM vault WHERE id = ? AND user_id = ?",
            [$id, $this->userId]
        );

        if (!$password) {
            return null;
        }

        $key = $this->deriveEncryptionKey($this->encryptionSalt);

        try {
            $password->password = $this->encryption->decrypt(
                $password->password->ciphertext,
                $password->nonce,
                $key
            );
            return $password;
        } catch (Exception $e) {
            return null;
        }
    }

    public function deletePassword(int $id): bool {
        try {
            $this->db->begin();
            $result = $this->db->query(
                "DELETE FROM vault WHERE id = ? AND user_id = ?",
                [$id, $this->userId]
            );
            $this->db->commit();
            return $result > 0;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function deriveEncryptionKey(string $salt): string {
        return hash_hmac('sha256', APP_KEY, $salt, true);
    }
}
