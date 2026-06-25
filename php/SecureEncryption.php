<?php
class SecureEncryption {
    private $key;

    public function __construct(string $key) {
        if (strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new Exception("Key must be " . SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES . " bytes long.");
        }
        $this->key = $key;
    }

    public function encrypt(string $plaintext, string $additionalKeyMaterial = ''): array {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $key = $additionalKeyMaterial ? hash_hmac('sha256', $this->key, $additionalKeyMaterial, true) : $this->key;

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $nonce,
            $nonce,
            $key
        );

        return [
            'ciphertext' => $ciphertext,
            'nonce' => $nonce
        ];
    }

    public function decrypt(string $ciphertext, string $nonce, string $additionalKeyMaterial = ''): string {
        $key = $additionalKeyMaterial ? hash_hmac('sha256', $this->key, $additionalKeyMaterial, true) : $this->key;

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            $nonce,
            $nonce,
            $key
        );

        if ($plaintext === false) {
            throw new Exception("Decryption failed - authentication tag mismatch");
        }

        return $plaintext;
    }
}

