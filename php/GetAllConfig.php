<?php

final class GetAllConfig{

    private static array $cache = [];

    public static function load(bool $reload = false): array {
        $baseDir = self::getBaseDir();
        $projectRoot = self::findProjectRoot($baseDir, 'config');

        $configFile = $projectRoot . '/config/config.ini';
        $cacheKey = realpath($configFile) ?: $configFile;

        if (!$reload && isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $raw = self::readFile($configFile);
        $raw = self::applyPlaceholders($raw, $projectRoot);

        $config = parse_ini_string($raw, true, INI_SCANNER_TYPED);
        if ($config === false) {
            throw new RuntimeException("Invalid INI syntax in: {$configFile}");
        }

        $config = self::mergeEnv($config);
        self::validate($config);

        self::$cache[$cacheKey] = $config;
        return $config;
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
            $candidate = $dir . DIRECTORY_SEPARATOR . $anchor;

            if (is_dir($candidate)) {
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

    private static function mergeEnv(array $config): array {
        foreach ($_ENV as $key => $value) {
            self::setByPath($config, $key, $value);
        }

        return $config;
    }

    private static function setByPath(array &$config, string $path, mixed $value): void {
        // support: DB_HOST -> db.host
        $path = strtolower(str_replace('_', '.', $path));
        $parts = explode('.', $path);

        $ref = &$config;

        foreach ($parts as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }

        $ref = $value;
    }

    private static function validate(array $config): void {
        $required = [
            'app.name',
            'app.env'
        ];

        foreach ($required as $key) {
            if (!self::has($config, $key)) {
                throw new RuntimeException("Missing config key: {$key}");
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

    public static function clearCache(): void {
        self::$cache = [];
    }
}
