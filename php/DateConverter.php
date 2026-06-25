<?php

class DateConverter {

    public static function convertRowDates(array $row, array $dateColumns, string $direction): array {
        foreach ($dateColumns as $field => $type) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            if ($row[$field] === null || $row[$field] === '') {
                continue;
            }

            if ($type === 'timestamp') {
                $type = 'datetime';
            }

            $converted = self::convertDates((string) $row[$field], $direction, $type);

            if ($converted !== null) {
                $row[$field] = $converted;
            }
        }

        return $row;
    }

    public static function convertDates(?string $value, string $direction, string $type): ?string {
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
            if ($type === 'date') {
                $date = self::parseGermanDate($value);
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
                $parts = preg_split('/\s+/', $value);
                if (count($parts) !== 2) {
                    return null;
                }

                $sqlDate = self::convertDates($parts[0], 'toSql', 'date');
                $sqlTime = self::convertDates($parts[1], 'toSql', 'time');

                if ($sqlDate === null || $sqlTime === null) {
                    return null;
                }

                return $sqlDate . ' ' . $sqlTime;
            }

            return null;
        }

        if ($direction === 'fromSql') {
            if ($type === 'date') {
                $date = self::parseSqlDate($value);
                if ($date === null) {
                    return null;
                }

                return sprintf('%02d.%02d.%04d', $date['day'], $date['month'], $date['year']);
            }

            if ($type === 'time') {
                $time = self::parseTime($value);
                if ($time === null) {
                    return null;
                }

                return sprintf('%02d:%02d', $time['hour'], $time['minute']);
            }

            if ($type === 'datetime') {
                $parts = preg_split('/\s+/', $value);
                if (count($parts) !== 2) {
                    return null;
                }

                $displayDate = self::convertDates($parts[0], 'fromSql', 'date');
                $displayTime = self::convertDates($parts[1], 'fromSql', 'time');

                if ($displayDate === null || $displayTime === null) {
                    return null;
                }

                return $displayDate . ' ' . $displayTime;
            }

            return null;
        }

        return null;
    }

    private static function parseGermanDate(string $value): ?array {
        $parts = explode('.', $value);

        if (count($parts) !== 3) {
            return null;
        }

        [$day, $month, $year] = $parts;

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

        return [
            'day' => $day,
            'month' => $month,
            'year' => $year,
        ];
    }

    private static function parseSqlDate(string $value): ?array {
        $parts = explode('-', $value);

        if (count($parts) !== 3) {
            return null;
        }

        [$year, $month, $day] = $parts;

        if ($year === '' || $month === '' || $day === '') {
            return null;
        }

        if (!ctype_digit($year) || !ctype_digit($month) || !ctype_digit($day)) {
            return null;
        }

        $year = (int) $year;
        $month = (int) $month;
        $day = (int) $day;

        if ($year < 1000 || $year > 9999) {
            return null;
        }

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return [
            'day' => $day,
            'month' => $month,
            'year' => $year,
        ];
    }

    private static function parseTime(string $value): ?array {
        $parts = explode(':', $value);

        if (count($parts) !== 2 && count($parts) !== 3) {
            return null;
        }

        $hour = $parts[0];
        $minute = $parts[1];
        $second = $parts[2] ?? '0';

        if ($hour === '' || $minute === '' || $second === '') {
            return null;
        }

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

        return [
            'hour' => $hour,
            'minute' => $minute,
            'second' => $second,
        ];
    }
}
