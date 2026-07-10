<?php

/**
 * ClassLoader
 * -----------
 * Unified autoloader and route helper for a PHP project.
 *
 * Responsibilities:
 *   - Scans configured source folders for PHP files.
 *   - Extracts fully-qualified class/interface/trait names via token parsing.
 *   - Builds a single autoload map file: /autoload/autoload_map.php
 *     Structure:
 *       [
 *         'classes' => [ FQCN => ['file' => string, 'mtime' => int] ],
 *         'routes'  => [ shortClassName => filePath ]
 *       ]
 *   - Registers an autoloader that:
 *       * Loads classes based on the generated map.
 *       * Verifies file modification times and rebuilds the map if entries are outdated.
 *   - Provides a simple routing helper by mapping "short" class names
 *     (basename of the FQCN) to files located in /GUI/ or /Api/ directories.
 *
 * Project root detection
 * ----------------------
 * The project root is determined by walking upwards from the start directory
 * until we find a directory named 'auatoload' on the highest level
 *
 * Requirements:
 *   - $anchor must exist directly inside the project root, e.g.:
 *         /project_root/autoload/
 *   - If no ancestor directory contains $anchor as an immediate child,
 *     detection fails and an exception is thrown.
 *
 * This approach avoids issues with symlinked script paths by relying on
 * a logical "anchor" artifact inside the project root.
 *
 * Usage:
 *   ClassLoader::load($anchor, $paths);
 *   - $anchor: name of a file or directory that exists inside the project root.
 *   - $paths: array of relative source paths to scan (e.g. ['src', 'lib']).
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
    public static function load(array $paths): void {
        if (!empty(self::$mapCache)) {
            return; // already initialized (cache)
        }

        // robust start dir (CLI + web)
        $start = $_SERVER['SCRIPT_FILENAME'] ?? getcwd();
        $startDir = is_file($start) ? dirname($start) : $start;

        // symlink-safe
        $startDir = realpath($startDir) ?: $startDir;

        $basePath = self::findProjectFolder($startDir, 'autoload');

        if (!$basePath) {
            throw new Exception("Base path containing 'autolad' not found.");
        }

        $autoloadDir = $basePath . '/autoload';
        $mapFile = $autoloadDir . '/autoload_map.php';

        self::$basePath = $basePath;
        self::$paths = $paths;
        self::$mapFile = $mapFile;

        if (!is_dir($autoloadDir)) {
            mkdir($autoloadDir, 0775, true);
        }

        // load or build map
        $map = is_file($mapFile) ? require $mapFile : self::buildAutoloadMap($basePath, $paths, $mapFile);

        self::$mapCache = $map;
        $classMap = $map['classes'];

        spl_autoload_register(function ($class) use (&$classMap, $basePath, $paths, $mapFile) {

            static $rebuilt = false;

            $entry = $classMap[$class] ?? null;

            if ($entry && is_file($entry['file'])) {
                if (filemtime($entry['file']) === $entry['mtime']) {
                    require_once $entry['file'];
                    return;
                }
            }

            if (!$rebuilt) {
                $rebuilt = true;

                $lockFile = $mapFile . '.lock';
                $fp = fopen($lockFile, 'c');

                if ($fp && flock($fp, LOCK_EX)) {

                    // another request may have already rebuilt → reload map first
                    if (is_file($mapFile)) {
                        $map = require $mapFile;
                        self::$mapCache = $map;
                        $classMap = $map['classes'];
                    }

                    $entry = $classMap[$class] ?? null;

                    if (!$entry || !is_file($entry['file']) || filemtime($entry['file']) !== $entry['mtime']) {
                        // still outdated → rebuild
                        $map = self::buildAutoloadMap($basePath, $paths, $mapFile);
                        self::$mapCache = $map;
                        $classMap = $map['classes'];
                    }

                    flock($fp, LOCK_UN);
                }

                if ($fp) {
                    fclose($fp);
                }

                // retry after rebuild / reload
                $entry = $classMap[$class] ?? null;
                if ($entry && is_file($entry['file'])) {
                    require_once $entry['file'];
                    return;
                }
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
        $cpaths = $paths;
        $guiRoutes = [];
        if (self::isMultiDimensional($paths)) {
            $cpaths = $paths['classes'];
            $guiRoutes = $paths['routes'];
        }


        foreach ($cpaths as $path) {
            $rii = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator("$basePath/$path", FilesystemIterator::SKIP_DOTS)
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
                    // Router: store naked class name as key
                    $short = basename(str_replace('\\', '/', $def));
                    $found = array_filter($guiRoutes, function ($p) use ($filePath){
                        return strpos($filePath, $p) !== false;
                    });
                    if ($found) {
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

    private static function isMultiDimensional(array $array): bool {
        foreach ($array as $value) {
            if (is_array($value)) {
                return true;
            }
        }
        return false;
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
        $tmpFile = $file . '.tmp';

        $content = "<?php\n\n" .
                "// Auto-generated combined autoload map. Do not edit manually.\n" .
                "// Generated on: " . date('Y-m-d H:i:s') . "\n\n" .
                "return " . var_export($data, true) . ";\n";

        // ensure directory exists
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
    }

        // write complete file first
        file_put_contents($tmpFile, $content, LOCK_EX);

        // atomic swap
        rename($tmpFile, $file);
    }

    /*
     * ***********************************************
     * $anchor must be a file or directory just one 
     * below project directory 
     * **********************************************
     */

    private static function findProjectFolder(string $startDir, string $anchor): string {
        $dir = $startDir;
        $found = null;

        while ($dir !== dirname($dir)) {

            // resolve real path (symlink-safe)
            $realDir = realpath($dir) ?: $dir;

            $path = $realDir . DIRECTORY_SEPARATOR . $anchor;

            if (file_exists($path)) {
                // keep updating → highest wins
                $found = $realDir;
            }

            $dir = dirname($dir);
        }

        if ($found !== null) {
            return $found;
        }

        throw new Exception("Project root containing '{$anchor}' not found.");
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
            // ?Rebuild route map if missing
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
