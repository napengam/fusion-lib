<?php

class keyManager {

    /**
     * Holt den Key aus dem Memory-Cache von GetAllCOnfig
     */
    private static function getKey(): string {
        return GetAllCOnfig::load()['salt']['salt'] ?? '';
    }

    /**
     * Erstellt einen fälschungssicheren Token für die GUI
     * * @param string $string  Die Daten (z.B. User-ID)
     * @param string $context Der Zweck (z.B. 'edit_user')
     */
    public static function make(string $string, string $context = ''): string {
        $salt = random_bytes(16);

        // Der Kontext wird mit in den HMAC aufgenommen (Versiegelung)
        $hmac = hash_hmac('sha256', $context . $salt . $string, self::getKey(), true);

        // Wir packen alles in ein binäres Paket
        // 'n' = 16-bit unsigned short (Big Endian) für die Kontext-Länge
        $binary = pack('n', strlen($context)) . $context . $salt . $hmac;

        return base64_encode($binary);
    }

    /**
     * Verifiziert den Token strikt gegen Daten und Zweck
     * * @param string $string          Die Daten, die zurückkommen
     * @param string $stored          Der Token aus der GUI
     * @param string $expectedContext Der erwartete Zweck an dieser Codestelle
     */
    public static function verify(string $string, string $stored, string $expectedContext = ''): bool {
        $decoded = base64_decode($stored, true);

        // Mindestlänge: 2 (Länge) + 16 (Salt) + 32 (HMAC) = 50 Bytes
        if ($decoded === false || strlen($decoded) < 50) {
            return false;
        }

        // 1. Kontext aus dem Paket extrahieren
        $contextLength = unpack('n', substr($decoded, 0, 2))[1];
        $actualContext = substr($decoded, 2, $contextLength);

        // 2. STRIKTER CHECK: Stimmt der Zweck im Token mit der Erwartung im Code überein?
        if ($actualContext !== $expectedContext) {
            return false;
        }

        // 3. Salt und Hash extrahieren
        $salt = substr($decoded, 2 + $contextLength, 16);
        $hash = substr($decoded, 2 + $contextLength + 16);

        // 4. Kryptografische Prüfung
        // Wir berechnen den Hash mit dem erwarteten Kontext neu
        $expectedHash = hash_hmac('sha256', $expectedContext . $salt . $string, self::getKey(), true);

        // Zeit-resistenter Vergleich
        return hash_equals($expectedHash, $hash);
    }
}
