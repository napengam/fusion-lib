<?php

/**
 * DateConverter
 *
 * WHAT:
 * Central utility for converting date, time and datetime values
 * between user input (localized formats) and SQL format.
 *
 * HOW:
 * - Uses strict manual parsing (no strtotime / DateTime guessing)
 * - Supports both directions:
 *     - toSql   (UI → database)
 *     - fromSql (database → UI)
 * - Supports arrays and objects
 * - Supports per-field type definitions:
 *     'date', 'time', 'datetime', 'timestamp'
 * - Supports locales:
 *     - de (default): 31.12.2024
 *     - en: 12/31/2024
 * - Supports strict and loose parsing:
 *     strict = exact format required
 *     loose  = allows minor variations (e.g. single digits)
 * - Tracks invalid fields via $errors reference
 *
 * DESIGN:
 * - No silent data corruption
 * - Invalid values are not converted
 * - Errors are collected instead of throwing exceptions
 * - Deterministic and predictable behavior
 *
 * USAGE:
 * $errors = [];
 * $row = DateConverter::convertLocalToSql($row, [
 *     'start_date' => 'date',
 *     'created_at' => 'datetime'
 * ], 'de', $errors);
 */
class DateConverter {

    public static function convertSqlToLocal(array|object $row, array $dateFields, string $locale = 'de', array &$errors = [], bool $strict = true): array|object {
        return self::convertRowDates($row, $dateFields, 'fromSql', $locale, $errors, $strict);
    }

    public static function convertLocalToSql(array|object $row, array $dateFields, string $locale = 'de', array &$errors = [], bool $strict = true): array|object {
        return self::convertRowDates($row, $dateFields, 'toSql', $locale, $errors, $strict);
    }

    public static function getInvalidDateFields(array|object $row, array $dateFields, string $locale = 'de', bool $strict = true): array {
        $errors = [];
        self::convertRowDates($row, $dateFields, 'toSql', $locale, $errors, $strict);
        return array_keys($errors);
    }

    public static function convertRowDates(array|object $row, array $dateColumns, string $direction, string $locale, array &$errors, bool $strict): array|object {
        $isArray = is_array($row);
        foreach ($dateColumns as $field => $type) {
            $exists = $isArray ? array_key_exists($field, $row) : property_exists($row, $field);
            if (!$exists) {
                continue;
            }
            $value = $isArray ? $row[$field] : $row->$field;
            if ($value === null || $value === '') {
                continue;
            }
            $converted = self::convertDates((string) $value, $direction, $type, $locale, $strict);
            if ($converted === null) {
                $errors[$field] = true;
                continue;
            }
            if ($isArray) {
                $row[$field] = $converted;
            } else {
                $row->$field = $converted;
            }
        }
        return $row;
    }

    public static function convertDates(?string $value, string $direction, string $type, string $locale, bool $strict): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if ($type === 'timestamp') {
            $type = 'datetime';
        }
        if ($direction === 'toSql') {
            return self::toSql($value, $type, $locale, $strict);
        }
        if ($direction === 'fromSql') {
            return self::fromSql($value, $type, $locale);
        }
        return null;
    }

    private static function toSql(string $value, string $type, string $locale, bool $strict): ?string {
        if ($type === 'date') {
            $date = self::parseLocalDate($value, $locale, $strict);
            if ($date === null) {
                return null;
            }
            return sprintf('%04d-%02d-%02d', $date['year'], $date['month'], $date['day']);
        }
        if ($type === 'time') {
            $time = self::parseTime($value);
            if ($time === null) {
                return null;
            }
            return sprintf('%02d:%02d:%02d', $time['hour'], $time['minute'], $time['second']);
        }
        if ($type === 'datetime') {
            $parts = preg_split('/[T\s]+/', $value);
            if (count($parts) !== 2) {
                return null;
            }
            $date = self::toSql($parts[0], 'date', $locale, $strict);
            $time = self::toSql($parts[1], 'time', $locale, $strict);
            if ($date === null || $time === null) {
                return null;
            }
            return $date . ' ' . $time;
        }
        return null;
    }

    private static function fromSql(string $value, string $type, string $locale): ?string {
        if ($type === 'date') {
            $date = self::parseSqlDate($value);
            if ($date === null) {
                return null;
            }
            return self::formatLocalDate($date, $locale);
        }
        if ($type === 'time') {
            $time = self::parseTime($value);
            if ($time === null) {
                return null;
            }
            return sprintf('%02d:%02d:%02d', $time['hour'], $time['minute'], $time['second']);
        }
        if ($type === 'datetime') {
            $parts = preg_split('/[T\s]+/', $value);
            if (count($parts) !== 2) {
                return null;
            }
            $date = self::fromSql($parts[0], 'date', $locale);
            $time = self::fromSql($parts[1], 'time', $locale);
            if ($date === null || $time === null) {
                return null;
            }
            return $date . ' ' . $time;
        }
        return null;
    }

    private static function parseLocalDate(string $value, string $locale, bool $strict): ?array {
        if ($locale === 'en') {
            return self::parseEnDate($value, $strict);
        }
        return self::parseGermanDate($value, $strict);
    }

    private static function formatLocalDate(array $date, string $locale): string {
        if ($locale === 'en') {
            return sprintf('%02d/%02d/%04d', $date['month'], $date['day'], $date['year']);
        }
        return sprintf('%02d.%02d.%04d', $date['day'], $date['month'], $date['year']);
    }

    private static function parseGermanDate(string $value, bool $strict): ?array {
        $parts = explode('.', $value);
        if (count($parts) !== 3) {
            return null;
        }
        [$day, $month, $year] = $parts;
        if (!$strict) {
            $day = ltrim($day, '0');
            $month = ltrim($month, '0');
        }
        if ($day === '' || $month === '' || $year === '') {
            return null;
        }
        if (!ctype_digit($day) || !ctype_digit($month) || !ctype_digit($year)) {
            return null;
        }
        $day = (int) $day;
        $month = (int) $month;
        $year = (int) $year;
        if ($year < 1000 || $year > 9999) {
            return null;
        }
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        return ['day' => $day, 'month' => $month, 'year' => $year];
    }

    private static function parseEnDate(string $value, bool $strict): ?array {
        $parts = explode('/', $value);
        if (count($parts) !== 3) {
            return null;
        }
        [$month, $day, $year] = $parts;
        if (!$strict) {
            $day = ltrim($day, '0');
            $month = ltrim($month, '0');
        }
        if (!ctype_digit($day) || !ctype_digit($month) || !ctype_digit($year)) {
            return null;
        }
        $day = (int) $day;
        $month = (int) $month;
        $year = (int) $year;
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        return ['day' => $day, 'month' => $month, 'year' => $year];
    }

    private static function parseSqlDate(string $value): ?array {
        $parts = explode('-', $value);
        if (count($parts) !== 3) {
            return null;
        }
        [$year, $month, $day] = $parts;
        if (!ctype_digit($year) || !ctype_digit($month) || !ctype_digit($day)) {
            return null;
        }
        $year = (int) $year;
        $month = (int) $month;
        $day = (int) $day;
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        return ['day' => $day, 'month' => $month, 'year' => $year];
    }

    private static function parseTime(string $value): ?array {
        $parts = explode(':', $value);
        if (count($parts) !== 2 && count($parts) !== 3) {
            return null;
        }
        $hour = $parts[0];
        $minute = $parts[1];
        $second = $parts[2] ?? '0';
        if (!ctype_digit($hour) || !ctype_digit($minute) || !ctype_digit($second)) {
            return null;
        }
        $hour = (int) $hour;
        $minute = (int) $minute;
        $second = (int) $second;
        if ($hour < 0 || $hour > 23) {
            return null;
        }
        if ($minute < 0 || $minute > 59) {
            return null;
        }
        if ($second < 0 || $second > 59) {
            return null;
        }
        return ['hour' => $hour, 'minute' => $minute, 'second' => $second];
    }
}
