<?php

/**
 * GetAllConfig loads and parses project configuration from INI files with:
 * - Automatic project path resolution and caching
 * - Placeholder replacement (__DOCUMENT_ROOT__, __URL__)
 * - Runtime environment detection (CLI vs web server)
 * - HTTPS/HTTP protocol detection
 * - Type-safe INI parsing with fallback validation
 * - Singleton-style caching to prevent repeated file reads
 * - Comprehensive error handling for missing files and invalid syntax
 */
final class GetAllConfig {

    private static array $cache = [];

    public static function load(string $project = ''): array {
        if (empty($project)) {
            throw new RuntimeException("Missing project directory name");
        }
        if (defined('PROJECT_DIR')) {
            $project = PROJECT_DIR; // from bootstrap.php ??
        } else {
            $project = self::findBasePath(__DIR__, $project);
        }
        // Compute project base path

        $configPath = "$project/config/config.ini";

        // Use absolute path as cache key
        $cacheKey = realpath($configPath) ?: $configPath;

        // Return cached
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        if (!is_file($configPath)) {
            throw new RuntimeException("Config file not found: $configPath");
        }

        $raw = file_get_contents($configPath);
        if ($raw === false) {
            throw new RuntimeException("Failed to read config file: $configPath");
        }

        // Replace placeholders
        $raw = str_replace(
                ['__DOCUMENT_ROOT__', '__URL__'],
                [dirname($project, 1), self::getProjectUrl($project, dirname($project, 1))],
                $raw
        );

        // Parse configuration
        $parsed = parse_ini_string($raw, true, INI_SCANNER_TYPED);
        if ($parsed === false) {
            throw new RuntimeException("Invalid INI syntax in: $configPath");
        }

        // Cache and return
        return self::$cache[$cacheKey] = $parsed;
    }

    /*
     * ***********************************************
     * $targetDir must be a directory just one 
     * below project directory 
     * **********************************************
     */

    private static function findBasePath(string $startPath, string $anchorPath): string {
        $dir = realpath($startPath);
        if ($dir === false) {
            throw new Exception('Invalid start path');
        }
        $anchor = basename(rtrim($anchorPath, '/\\'));
        while ($dir !== dirname($dir)) {
            if (file_exists($dir . DIRECTORY_SEPARATOR . $anchor)) {
                return $dir;
            }
            $dir = dirname($dir);
        }
        throw new Exception("Anchor '{$anchor}' not found from '{$startPath}'");
    }

    private static function getProjectUrl(string $project, string $basePath): string {
        // Fallback for CLI
        if (PHP_SAPI === 'cli') {
            return "file://$basePath/$project/";
        }

        $protocol = 'http://';
        if (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                ($_SERVER['SERVER_PORT'] ?? 80) == 443 ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ) {
            $protocol = 'https://';
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? "/$project/";

        $urlParts = explode("/$project/", $uri);
        $rootUrl = rtrim($protocol . $host . $urlParts[0], '/');

        return "$rootUrl/$project/";
    }
}
