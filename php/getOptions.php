<?php
/**
 * Class GetOptions
 *
 * WHAT:
 * Lightweight configuration loader for CLI applications.
 *
 * Combines:
 * - Command line arguments (argv)
 * - INI configuration file
 *
 * into one unified configuration array.
 *
 * HOW:
 * - Parses CLI arguments manually from $argv
 * - Supports key-value options (e.g. -logfile app.log)
 * - Supports boolean flags (e.g. -console)
 * - Loads an INI file (default: ini.ini or via -i option)
 * - Merges CLI options over INI values (CLI overrides take priority)
 *
 * DESIGN:
 * - Deterministic and simple parsing (no getopt dependency)
 * - Only predefined options are accepted (whitelist via $expected)
 * - Fails early if config file is missing or invalid
 * - Returns a flat associative array for easy consumption
 *
 * NOTES:
 * - CLI options must be passed as: -key value OR -flag
 * - Option names are normalized without leading "-"
 * - INI file is parsed using INI_SCANNER_TYPED (preserves types)
 *
 * USE CASES:
 * - CLI scripts
 * - cron jobs
 * - small tools needing config + runtime overrides
 */

class GetOptions {

    private array $config = [];

    public function __construct(array $expected = ['-i', '-logfile', '-address', '-console'], string $defaultIni = 'ini.ini') {
        $cliOptions = $this->getOptArgv($expected);

        $iniFile = $cliOptions['i'] ?? $defaultIni;
        if (!file_exists($iniFile)) {
            throw new RuntimeException("Config file not found: $iniFile");
        }
        $ini = parse_ini_file($iniFile, false, INI_SCANNER_TYPED);

        if ($ini === false) {
            throw new RuntimeException("Failed to parse ini file: $iniFile");
        }

        // Merge CLI overrides on top of ini config
        $this->config = array_replace($ini, $cliOptions);
    }

    public function getConfig(): array {
        return $this->config;
    }

    private function getOptArgv(array $expect): array {
        global $argv, $argc;
        $out = [];

        for ($i = 1; $i < $argc; $i++) {
            if (!in_array($argv[$i], $expect, true)) {
                continue;
            }

            $exp = ltrim($argv[$i], '-');

            if ($i + 1 < $argc && $argv[$i + 1][0] !== '-') {
                $i++;
                $out[$exp] = $argv[$i];
            } else {
                $out[$exp] = true; // boolean flag
            }
        }

        return $out;
    }
}
