<?php

declare(strict_types=1);

trait DateAndTime {

    /**
     * Get current date (Y-m-d)
     */
    private function getCurrentDate(): string {
        return date('Y-m-d');
    }

    /**
     * Get current time (H:i)
     */
    private function getCurrentTime(): string {
        return date('H:i');
    }

    /**
     * Public alias for current time.
     */
    public function timeNow(): string {
        return $this->getCurrentTime();
    }

    /**
     * Public alias for current date.
     */
    public function fullDateNow(): string {
        return $this->getCurrentDate();
    }

    /**
     * Get current timestamp (Y-m-d H:i:s)
     */
    public function timestampNow(): string {
        return date('Y-m-d H:i:s');
    }

    /**
     * Calculate duration in minutes between two HH:MM times.
     * Handles rollover past midnight.
     */
    public function durationMinutes(string $start, string $end): int {
        $startTime = strtotime("1970-01-01 $start");
        $endTime = strtotime("1970-01-01 $end");

        if ($endTime < $startTime) {
            $endTime += 86400; // add 24h rollover
        }

        return (int) (($endTime - $startTime) / 60);
    }

    /**
     * Add a time unit (days, months, years) to a date.
     */
    public function dateAddWhat(string $date, string $unit, int $amount): string {
        $mod = match ($unit) {
            'd' => "+$amount days",
            'm' => "+$amount months",
            'y' => "+$amount years",
            default => null,
        };

        return $mod ? date('Y-m-d', strtotime($mod, strtotime($date))) : $date;
    }

    /**
     * Add hours and minutes to a time (H:i)
     */
    public function timeAdd(string $time, int $hours, int $minutes): string {
        return date('H:i', strtotime("+{$hours} hours +{$minutes} minutes", strtotime($time)));
    }

    /**
     * Convert German date (d.m.Y) to SQL (Y-m-d)
     */
    public function sqlDateSet(string $date): string {
        $parts = explode('.', $date);
        return count($parts) === 3 ? sprintf('%04d-%02d-%02d', (int) $parts[2], (int) $parts[1], (int) $parts[0]) : $date;
    }

    /**
     * Convert SQL date (Y-m-d) to German format (d.m.Y)
     */
    public function gerDateSet(string $date): string {
        $parts = explode('-', $date);
        return count($parts) === 3 ? sprintf('%02d.%02d.%04d', (int) $parts[2], (int) $parts[1], (int) $parts[0]) : $date;
    }

    /**
     * Validate a time string (H:i[:s])
     */
    public function isValidTime(string $time): bool {
        return (bool) preg_match('/^(2[0-3]|[01]?\d):([0-5]?\d)(:[0-5]?\d)?$/', $time);
    }

    /**
     * Validate any supported date format.
     */
    public function isValidDate(?string $anyDate): bool {
        if (trim((string) $anyDate) === '') {
            return true;
        }
        return $this->parseFlexibleDate($anyDate) !== false;
    }

    /**
     * Check leap year.
     */
    public function isLeapYear(int $year): bool {
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }

    /**
     * Check if date is in the past.
     */
    public function isPastDate(string $date): bool {
        $parsed = $this->parseFlexibleDate($date);
        return $parsed !== false && $parsed['datetime']->getTimestamp() < strtotime($this->getCurrentDate());
    }

    /**
     * Check if date is in the future.
     */
    public function isFutureDate(string $date): bool {
        $parsed = $this->parseFlexibleDate($date);
        return $parsed !== false && $parsed['datetime']->getTimestamp() > strtotime($this->getCurrentDate());
    }

    /**
     * Flexible date/time parser supporting various formats.
     */
    public function parseFlexibleDate(string $input): array|false {
        $input = trim($input);
        if ($input === '') {
            return false;
        }

        // Extend time with :00 if needed
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $input) ||
                preg_match('/^\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4} \d{2}:\d{2}$/', $input)) {
            $input .= ':00';
        }

        $input = $this->normDateTime($input);
        if ($input === false) {
            return false;
        }

        $formats = [
            'Y-m-d H:i:s', 'd.m.Y H:i:s', 'd/m/Y H:i:s', 'd-m-Y H:i:s',
            'Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y',
        ];

        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $input);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($dt && empty($errors['warning_count']) && empty($errors['error_count'])) {
                return [
                    'datetime' => $dt,
                    'hasTime' => str_contains($format, 'H'),
                ];
            }
        }

        $timestamp = strtotime($input);
        if ($timestamp !== false) {
            return [
                'datetime' => (new \DateTimeImmutable())->setTimestamp($timestamp),
                'hasTime' => str_contains($input, ':'),
            ];
        }

        return false;
    }

    /**
     * Normalize date/time to standard SQL-safe format.
     */
    public function normDateTime(string $input): string|false {
        $input = trim($input);
        if ($input === '') {
            return false;
        }

        // Match dd.mm.yyyy [HH:MM[:SS]] or similar
        if (!preg_match('/^(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4})(?:\s+(\d{1,2}:\d{2}(?::\d{2})?))?$/', $input, $m)) {
            return false;
        }

        $date = $m[1];
        $time = $m[2] ?? null;

        // Pad day/month/year
        $sep = preg_match('/[.\-\/]/', $date, $s) ? $s[0] : '-';
        $parts = explode($sep, $date);
        $parts = array_map(fn($p) => str_pad($p, (strlen($p) <= 2 ? 2 : 4), '0', STR_PAD_LEFT), $parts);
        $date = implode($sep, $parts);

        if ($time === null) {
            return $date;
        }

        if (!$this->isValidTime($time)) {
            return false;
        }

        $timeParts = explode(':', $time);
        $timeParts = array_pad($timeParts, 3, '00');
        $time = sprintf('%02d:%02d:%02d', ...$timeParts);

        return "$date $time";
    }

    /**
     * Locale-aware conversion between user format and SQL (Y-m-d[ H:i:s]).
     */
    public function convertDates(mixed $input, ?string $locale = null, bool $toSql = true): string|array|false {
        static $formatters = [];
        $locale ??= \Locale::getDefault();
        $timezone = date_default_timezone_get();

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

        $input = $this->normDateTime($input);
        if ($input === false) {
            return false;
        }

        if ($toSql) {
            // Locale → SQL
            $key = "{$locale}_SHORT_SHORT_parse";
            $fmt = $formatters[$key] ??= new \IntlDateFormatter($locale, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT, $timezone);

            $ts = $fmt->parse($input);
            if ($ts === false) {
                $key = "{$locale}_SHORT_NONE_parse";
                $fmt = $formatters[$key] ??= new \IntlDateFormatter($locale, \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE, $timezone);
                $ts = $fmt->parse($input);
                if ($ts === false) {
                    return false;
                }
                return date('Y-m-d', $ts);
            }

            return str_contains($input, ':') ? date('Y-m-d H:i:s', $ts) : date('Y-m-d', $ts);
        }

        // SQL → Locale
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $input) ?: \DateTimeImmutable::createFromFormat('Y-m-d', $input);
        if (!$dt) {
            return false;
        }

        $hasTime = str_contains($input, ':');
        $key = "{$locale}_SHORT_" . ($hasTime ? 'SHORT' : 'NONE') . "_format";
        $fmt = $formatters[$key] ??= new \IntlDateFormatter($locale, \IntlDateFormatter::SHORT, $hasTime ? \IntlDateFormatter::SHORT : \IntlDateFormatter::NONE, $timezone);

        return $fmt->format($dt);
    }
}
