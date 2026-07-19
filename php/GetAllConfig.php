<?php

final class GetAllConfig {

    private static array $cache = [];
    private static bool $envLoaded = false;

    public static function load(bool $reload = false, array $requiredKeys = []): array {
        $baseDir = self::getBaseDir();
        $projectRoot = self::findProjectRoot($baseDir, 'config');

        // ✅ auto-discover and load env files
        self::loadEnvFiles($projectRoot);

        $configFile = $projectRoot . '/config/config.ini';
        $cacheKey = realpath($configFile) ?: $configFile;

        if (!$reload && isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $raw = self::readFile($configFile);

        // ✅ order matters
        $raw = self::applyEnv($raw);                     // ${VAR}
        $raw = self::applyPlaceholders($raw, $projectRoot); // __XYZ__

        $config = parse_ini_string($raw, true, INI_SCANNER_TYPED);
        if ($config === false) {
            throw new RuntimeException("Invalid INI syntax in: {$configFile}");
        }

        if ($requiredKeys) {
            self::validate($config, $requiredKeys);
        }

        self::$cache[$cacheKey] = $config;
        return $config;
    }

    /**
     * ✅ NEW: handles multiple env files + discovery
     */
    private static function loadEnvFiles(string $startDir): void {
        if (self::$envLoaded) {
            return;
        }

        // 🔍 find nearest .env going upwards
        $envBase = self::findUpwards($startDir, '.env');

        if ($envBase === null) {
            self::$envLoaded = true;
            return;
        }

        $dir = dirname($envBase);

        // ✅ load in correct priority order
        $files = [
            $dir . '/.env',
            $dir . '/.env.local',
            $dir . '/.env-' . $startDir
        ];

        // optional environment-specific file
        $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null;
        if ($appEnv) {
            $files[] = $dir . "/.env.{$appEnv}";
        }

        foreach ($files as $file) {
            self::loadEnv($file);
        }

        self::$envLoaded = true;
    }

    /**
     * 🔍 walk up directory tree to find file
     */
    private static function findUpwards(string $startDir, string $filename): ?string {
        $dir = $startDir;

        while ($dir !== dirname($dir)) {
            $candidate = $dir . DIRECTORY_SEPARATOR . $filename;
            if (is_file($candidate)) {
                return $candidate;
            }
            $dir = dirname($dir);
        }

        return null;
    }

    private static function loadEnv(string $file): void {
        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new RuntimeException("Cannot read .env file: {$file}");
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);

            // ✅ remove quotes safely
            $value = preg_replace('/^([\'"])(.*)\1$/', '$2', $value);

            // ✅ type casting
            $lower = strtolower($value);
            if ($lower === 'true') {
                $value = true;
            } elseif ($lower === 'false') {
                $value = false;
            } elseif ($lower === 'null') {
                $value = null;
            } elseif (is_numeric($value)) {
                $value = $value + 0;
            }

            // ✅ do not overwrite existing env
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    private static function applyEnv(string $raw): string {
        return preg_replace_callback('/\$\{([A-Z0-9_]+)(:-([^}]*))?\}/', function ($m) {
            $key = $m[1];
            $default = $m[3] ?? null;

            if (isset($_ENV[$key])) {
                return (string) $_ENV[$key];
            }

            if ($default !== null) {
                return $default;
            }

            // keep original if not found
            return $m[0];
        }, $raw);
    }

    private static function getBaseDir(): string {
        $path = $_SERVER['SCRIPT_FILENAME'] ?? getcwd();
        $real = realpath($path);

        if ($real === false) {
            throw new RuntimeException("Cannot resolve base path");
        }

        return is_file($real) ? dirname($real) : $real;
    }

    private static function findProjectRoot(string $startDir, string $anchor): string {
        $dir = $startDir;

        while ($dir !== dirname($dir)) {
            if (is_dir($dir . DIRECTORY_SEPARATOR . $anchor)) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        throw new RuntimeException("Project root with '{$anchor}' not found");
    }

    private static function readFile(string $file): string {
        if (!is_file($file)) {
            throw new RuntimeException("Config not found: {$file}");
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException("Cannot read config: {$file}");
        }

        return $content;
    }

    private static function applyPlaceholders(string $raw, string $projectRoot): string {
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? $projectRoot;
        $url = self::detectUrl($projectRoot);

        return str_replace(
            ['__DOCUMENT_ROOT__', '__PROJECT_ROOT__', '__URL__'],
            [$documentRoot, $projectRoot, $url],
            $raw
        );
    }

    private static function detectUrl(string $projectRoot): string {
        if (PHP_SAPI === 'cli') {
            return 'file://' . $projectRoot . '/';
        }

        $https = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            ($_SERVER['SERVER_PORT'] ?? 80) == 443 ||
            ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        );

        $scheme = $https ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(dirname($scriptName), '/\\');

        return $scheme . $host . ($basePath ? $basePath : '') . '/';
    }

    private static function validate(array $config, array $required) {
        foreach ($required as $key) {
            $value = self::config_get($config, $key);

            if ($value === null || $value === '') {
                throw new RuntimeException("Missing config: $key");
            }
        }
    }

    private static function has(array $config, string $path): bool {
        $parts = explode('.', $path);
        $ref = $config;

        foreach ($parts as $part) {
            if (!array_key_exists($part, $ref)) {
                return false;
            }
            $ref = $ref[$part];
        }

        return true;
    }

    private static function config_get(array $config, string $key) {
        $parts = explode('.', $key);

        foreach ($parts as $part) {
            if (!isset($config[$part])) {
                return null;
            }
            $config = $config[$part];
        }

        return $config;
    }

    public static function clearCache(): void {
        self::$cache = [];
        self::$envLoaded = false;
    }

    public static function get(string $path, mixed $default = null): mixed {
        $config = self::load();
        $parts = explode('.', $path);
        $value = $config;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }
}