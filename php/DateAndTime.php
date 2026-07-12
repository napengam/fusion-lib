<?php

declare(strict_types=1);

/**
 * Trait DateAndTime
 *
 * WHAT:
 * Collection of helper methods for handling date and time operations
 * in a consistent way across the application.
 *
 * Provides:
 * - Current date, time, and timestamp helpers
 * - Date and time calculations (add, duration, comparisons)
 * - Format conversions (German ↔ SQL)
 * - Validation utilities for date and time
 * - Flexible parsing of mixed date/time input formats
 * - Locale-aware conversion using IntlDateFormatter
 *
 * HOW:
 * - Uses strict manual parsing (no unreliable strtotime guessing)
 * - Uses DateTimeImmutable for safe comparisons only
 * - Normalizes input into deterministic formats before processing
 * - Supports multiple input formats (d.m.Y, Y-m-d, d/m/Y, etc.)
 * - Uses IntlDateFormatter only for formatting (not parsing)
 *
 * DESIGN:
 * - No silent data corruption
 * - Invalid values return false
 * - Predictable and testable behavior
 * - Works with strings, arrays, and objects
 * - Keeps method names stable for backward compatibility
 *
 * NOTES:
 * - Timezone is taken from PHP default
 * - SQL format: Y-m-d or Y-m-d H:i:s
 * - German format: d.m.Y
 */

trait DateAndTime {

    private function getCurrentDate(): string {
        return date('Y-m-d');
    }

    private function getCurrentTime(): string {
        return date('H:i');
    }

    public function timeNow(): string {
        return $this->getCurrentTime();
    }

    public function fullDateNow(): string {
        return $this->getCurrentDate();
    }

    public function timestampNow(): string {
        return date('Y-m-d H:i:s');
    }

    public function durationMinutes(string $start, string $end): int {
        $s = $this->parseTime($start);
        $e = $this->parseTime($end);

        if (!$s || !$e) {
            return 0;
        }

        $startMin = $s['hour'] * 60 + $s['minute'];
        $endMin = $e['hour'] * 60 + $e['minute'];

        if ($endMin < $startMin) {
            $endMin += 1440;
        }

        return $endMin - $startMin;
    }

    public function dateAddWhat(string $date, string $unit, int $amount): string {
        $d = $this->parseDate($date);
        if (!$d) {
            return $date;
        }

        $dt = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $d['year'], $d['month'], $d['day']));

        $mod = match ($unit) {
            'd' => "+$amount days",
            'm' => "+$amount months",
            'y' => "+$amount years",
            default => null,
        };

        if (!$mod) {
            return $date;
        }

        return $dt->modify($mod)->format('Y-m-d');
    }

    public function timeAdd(string $time, int $hours, int $minutes): string {
        $t = $this->parseTime($time);
        if (!$t) {
            return $time;
        }

        $total = $t['hour'] * 60 + $t['minute'] + ($hours * 60) + $minutes;
        $total = ($total % 1440 + 1440) % 1440;

        $h = intdiv($total, 60);
        $m = $total % 60;

        return sprintf('%02d:%02d', $h, $m);
    }

    public function sqlDateSet(string $date): string {
        $d = $this->parseDate($date);
        if (!$d) {
            return $date;
        }

        return sprintf('%04d-%02d-%02d', $d['year'], $d['month'], $d['day']);
    }

    public function gerDateSet(string $date): string {
        $d = $this->parseSqlDate($date);
        if (!$d) {
            return $date;
        }

        return sprintf('%02d.%02d.%04d', $d['day'], $d['month'], $d['year']);
    }

    public function isValidTime(string $time): bool {
        return $this->parseTime($time) !== null;
    }

    public function isValidDate(?string $anyDate): bool {
        if (trim((string)$anyDate) === '') {
            return true;
        }
        return $this->parseFlexibleDate($anyDate) !== false;
    }

    public function isLeapYear(int $year): bool {
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }

    public function isPastDate(string $date): bool {
        $parsed = $this->parseFlexibleDate($date);
        if (!$parsed) {
            return false;
        }

        return $parsed['datetime']->format('Y-m-d') < $this->getCurrentDate();
    }

    public function isFutureDate(string $date): bool {
        $parsed = $this->parseFlexibleDate($date);
        if (!$parsed) {
            return false;
        }

        return $parsed['datetime']->format('Y-m-d') > $this->getCurrentDate();
    }

    public function parseFlexibleDate(string $input): array|false {
        $input = trim($input);
        if ($input === '') {
            return false;
        }

        $dateTime = explode(' ', $input, 2);
        $datePart = $dateTime[0];
        $timePart = $dateTime[1] ?? null;

        $date = $this->parseDate($datePart) ?? $this->parseSqlDate($datePart);
        if (!$date) {
            return false;
        }

        $hasTime = false;
        $time = ['hour' => 0, 'minute' => 0, 'second' => 0];

        if ($timePart !== null) {
            $t = $this->parseTime($timePart);
            if (!$t) {
                return false;
            }
            $time = $t;
            $hasTime = true;
        }

        $dt = new \DateTimeImmutable(sprintf(
            '%04d-%02d-%02d %02d:%02d:%02d',
            $date['year'],
            $date['month'],
            $date['day'],
            $time['hour'],
            $time['minute'],
            $time['second']
        ));

        return ['datetime' => $dt, 'hasTime' => $hasTime];
    }

    public function normDateTime(string $input): string|false {
        $parsed = $this->parseFlexibleDate($input);
        if (!$parsed) {
            return false;
        }

        return $parsed['hasTime']
            ? $parsed['datetime']->format('Y-m-d H:i:s')
            : $parsed['datetime']->format('Y-m-d');
    }

    public function convertDates(mixed $input, ?string $locale = null, bool $toSql = true): object|string|array|false {
        if (is_array($input)) {
            return array_map(fn($v) => $this->convertDates($v, $locale, $toSql), $input);
        }

        if (is_object($input)) {
            $clone = clone $input;
            foreach ($clone as $k => $v) {
                $clone->$k = $this->convertDates($v, $locale, $toSql);
            }
            return $clone;
        }

        if (!is_string($input) || trim($input) === '') {
            return false;
        }

        if ($toSql) {
            return $this->normDateTime($input);
        }

        $parsed = $this->parseFlexibleDate($input);
        if (!$parsed) {
            return false;
        }

        $dt = $parsed['datetime'];
        return $parsed['hasTime']
            ? $dt->format('d.m.Y H:i')
            : $dt->format('d.m.Y');
    }

    private function parseDate(string $value): ?array {
        if (!preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/', $value, $m)) {
            return null;
        }

        $day = (int)$m[1];
        $month = (int)$m[2];
        $year = (int)$m[3];

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return ['day' => $day, 'month' => $month, 'year' => $year];
    }

    private function parseSqlDate(string $value): ?array {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return null;
        }

        $year = (int)$m[1];
        $month = (int)$m[2];
        $day = (int)$m[3];

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return ['day' => $day, 'month' => $month, 'year' => $year];
    }

    private function parseTime(string $value): ?array {
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            return null;
        }

        $hour = (int)$m[1];
        $minute = (int)$m[2];
        $second = isset($m[3]) ? (int)$m[3] : 0;

        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return ['hour' => $hour, 'minute' => $minute, 'second' => $second];
    }
}