<?php

final class AppContext {

    private static string $projectDir;
    private static array $config;

    public static function init(string $projectDir): void {
        self::$projectDir = $projectDir;
        self::$config = GetAllConfig::load($projectDir);
    }

    public static function config(): array {
        return self::$config;
    }
}
