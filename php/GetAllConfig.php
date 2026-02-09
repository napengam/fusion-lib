<?php

final class GetAllConfig
{
    private static array $cache = [];

    public static function load(string $project = ''): array
    {
        if (empty($project)) {
            throw new RuntimeException("Missing project directory name");
        }

        // Compute project base path
        $dir = str_replace('\\', '/', __DIR__);
        $parts = explode("/$project", $dir);

        if (count($parts) < 2) {
            throw new RuntimeException("Unable to resolve project base path for: $project (searched in $dir)");
        }

        $basePath = $parts[0];
        $configPath = "$basePath/$project/config/config.ini";

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
            [$basePath, self::getProjectUrl($project, $basePath)],
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

    private static function getProjectUrl(string $project, string $basePath): string
    {
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
        $uri  = $_SERVER['REQUEST_URI'] ?? "/$project/";

        $urlParts = explode("/$project/", $uri);
        $rootUrl  = rtrim($protocol . $host . $urlParts[0], '/');

        return "$rootUrl/$project/";
    }
}
