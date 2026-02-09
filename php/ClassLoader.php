<?php

/**
 * ClassLoader
 * ------------
 * Unified autoloader + router helper.
 *
 * Generates one file: /autoload/autoload_map.php
 * Structure:
 *   [
 *     'classes' => [ className => ['file' => ..., 'mtime' => ...] ],
 *     
 *     'routes'  => [ shortClassName => filePath ]
 *   ]
 */
class ClassLoader {

    /** Unified in-memory cache of the loaded map */
    private static array $mapCache = [];
    private static string $basePath;
    private static array $paths;
    private static string $mapFile;

    /**
     * Initialize the autoloader.
     */
    public static function load(string $projectFolder, array $paths): void {
        $baseDir = dirname(__DIR__);
        $basePath = self::findBasePath($baseDir, $projectFolder);

        if (!$basePath) {
            throw new Exception("Base path containing '{$projectFolder}' not found.");
        }

        $autoloadDir = $basePath . '/autoload';
        $mapFile = $autoloadDir . '/autoload_map.php';

        self::$basePath = $basePath;
        self::$paths = $paths;
        self::$mapFile = $basePath . '/autoload/autoload_map.php';

        if (!is_dir($autoloadDir)) {
            mkdir($autoloadDir, 0775, true);
        }

        // Load or build unified map
        $map = is_file($mapFile) ? require $mapFile : self::buildAutoloadMap($basePath, $paths, $mapFile);

        self::$mapCache = $map;
        $classMap = $map['classes'];

        // Register PSR-like autoloader
        spl_autoload_register(function ($class) use (&$classMap, $basePath, $paths, $mapFile) {
            $entry = $classMap[$class] ?? null;

            if ($entry && is_file($entry['file'])) {
                if (filemtime($entry['file']) === $entry['mtime']) {
                    require_once $entry['file'];
                    return;
                }
            }

            // Rebuild map if missing/outdated
            $map = self::buildAutoloadMap($basePath, $paths, $mapFile);
            self::$mapCache = $map;
            $classMap = $map['classes'];

            $entry = $classMap[$class] ?? null;
            if ($entry && is_file($entry['file'])) {
                require_once $entry['file'];
                return;
            }

            throw new Exception("Class '{$class}' not found or outdated.");
        });
    }

    /**
     * Build unified map and write to disk.
     */
    private static function buildAutoloadMap(string $basePath, array $paths, string $mapFile): array {
        $classes = [];
        $routes = [];

        foreach ($paths as $path) {
            $fullPath = self::resolvePath($basePath, $path);

            if (!is_dir($fullPath)) {
                continue;
            }

            $rii = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($rii as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $filePath = str_replace('\\', '/', $file->getPathname());
                $defs = self::extractDefinitions($filePath);

                foreach ($defs as $def) {
                    $classes[$def] = [
                        'file' => $filePath,
                        'mtime' => filemtime($filePath),
                    ];

                    if (strpos($filePath, '/GUI/') !== false ||
                            strpos($filePath, '/Api/') !== false ||
                            strpos($filePath, '/Controller/') !== false) {
                        // Router: store naked class name as key
                        $short = basename(str_replace('\\', '/', $def));
                        $routes[$short] = $filePath;
                    }
                }
            }
        }

        ksort($classes);
        ksort($routes);

        $data = [
            'classes' => $classes,
            'routes' => $routes,
        ];

        self::writeMapFile($mapFile, $data);
        return $data;
    }

    /**
     * Extracts PHP class/interface/trait names from a file.
     */
    private static function extractDefinitions(string $file): array {
        if (!is_file($file) || pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
            return [];
        }

        $contents = file_get_contents($file);
        $tokens = token_get_all($contents);
        $defs = [];
        $namespace = '';

        for ($i = 0; $i < count($tokens); $i++) {
            if (!is_array($tokens[$i])) {
                continue;
            }

            // Capture namespace
            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = '';
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if (is_array($tokens[$j]) &&
                            ($tokens[$j][0] === T_STRING || $tokens[$j][0] === T_NAME_QUALIFIED)) {
                        $namespace .= $tokens[$j][1];
                    } elseif ($tokens[$j] === ';' || $tokens[$j] === '{') {
                        break;
                    }
                }
            }

            // Capture class/interface/trait
            if (in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                $prev = $tokens[$i - 1] ?? null;
                if ($tokens[$i][0] === T_CLASS && is_array($prev) && $prev[0] === T_NEW) {
                    continue; // Skip anonymous
                }

                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $name = $tokens[$j][1];
                        $defs[] = ($namespace ? $namespace . '\\' : '') . $name;
                        break;
                    }
                }
            }
        }

        return $defs;
    }

    /**
     * Writes the unified autoload map file.
     */
    private static function writeMapFile(string $file, array $data): void {
        file_put_contents(
                $file,
                "<?php\n\n" .
                "// Auto-generated combined autoload map. Do not edit manually.\n" .
                "// Generated on: " . date('Y-m-d H:i:s') . "\n\n" .
                "return " . var_export($data, true) . ";\n"
        );
    }

    /**
     * Resolve relative or absolute directory paths consistently.
     */
    private static function resolvePath(string $basePath, string $path): string {
        // If path starts with "/" (Unix) or drive letter (Windows), treat as absolute
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Z]:[\\\\\\/]/i', $path)) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        // Otherwise, treat as relative to base
        $resolved = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
        return realpath($resolved) ?: $resolved;
    }

    /**
     * Finds the base project directory by folder name.
     */
    private static function findBasePath(string $startPath, string $targetFolder): ?string {
        $parts = explode(DIRECTORY_SEPARATOR, $startPath);
        $path = [];

        foreach ($parts as $part) {
            $path[] = $part;
            if ($part === $targetFolder) {
                return implode(DIRECTORY_SEPARATOR, $path);
            }
        }

        return null;
    }

    // -------------------------------
    // 🔹 Public helper methods
    // -------------------------------

    public static function getMap(): array {
        return self::$mapCache;
    }

    public static function getRoutes(): array {
        return self::$mapCache['routes'] ?? [];
    }

    public static function getFileHash(string $relativePath): ?string {
        return self::$mapCache['hashes'][$relativePath] ?? null;
    }

    /**
     * Dynamically instantiate a controller by short name
     * Example: ClassLoader::createRoute('DashboardController');
     */
    public static function createRoute(string $shortName): bool {
        $routes = self::$mapCache['routes'] ?? [];

        if (!isset($routes[$shortName])) {
            // Rebuild route map if missing
            $map = self::buildAutoloadMap(self::$basePath, self::$paths, self::$mapFile);
            self::$mapCache = $map;
            $routes = $map['routes'] ?? [];

            if (!isset($routes[$shortName])) {
                return false; // still not found
            }
        }

        $file = $routes[$shortName] ?? null;
        if ($file && is_file($file)) {
            require_once $file;
            return true;
        }

        return false;
    }
}
