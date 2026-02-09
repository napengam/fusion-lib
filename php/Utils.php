<?php
declare(strict_types=1);

use DateTime;
use Exception;
use IntlDateFormatter;

class Utils
{
    /**
     * Recursively returns a sanitized copy of the input data
     * without modifying the original input.
     *
     * @param mixed $data Object, array, or scalar
     * @return mixed Sanitized version of the input
     */
    public static function clean($data)
    {
        if (is_string($data)) {
            return htmlentities($data, ENT_QUOTES, 'UTF-8');
        }

        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleaned[$key] = self::clean($value);
            }
            return $cleaned;
        }

        if ($data instanceof ArrayObject) {
            $copy = new ArrayObject();
            foreach ($data as $key => $value) {
                $copy[$key] = self::clean($value);
            }
            return $copy;
        }

        if (is_object($data)) {
            $cleaned = clone $data;
            foreach (get_object_vars($cleaned) as $key => $value) {
                $cleaned->$key = self::clean($value);
            }
            return $cleaned;
        }

        return $data;
    }

    /**
     * Recursively decode htmlentities.
     *
     * @param mixed $data
     * @return mixed Decoded version
     */
    public static function decode($data)
    {
        if (is_string($data)) {
            return html_entity_decode($data, ENT_QUOTES, 'UTF-8');
        }

        if (is_array($data)) {
            $decoded = [];
            foreach ($data as $key => $value) {
                $decoded[$key] = self::decode($value);
            }
            return $decoded;
        }

        if ($data instanceof ArrayObject) {
            $copy = new ArrayObject();
            foreach ($data as $key => $value) {
                $copy[$key] = self::decode($value);
            }
            return $copy;
        }

        if (is_object($data)) {
            $decoded = clone $data;
            foreach (get_object_vars($decoded) as $key => $value) {
                $decoded->$key = self::decode($value);
            }
            return $decoded;
        }

        return $data;
    }

    /**
     * Build lookup arrays for value-text mappings.
     *
     * @param array $options
     * @return array
     */
    private static function buildLookupArrays(array $options): array
    {
        $valueToText = [];
        $textToValue = [];

        foreach ($options as $option) {
            if (strpos($option, '|') !== false) {
                list($value, $text) = explode('|', $option, 2);
            } else {
                $value = $option;
                $text = $option;
            }

            $valueToText[$value] = $text;
            $textToValue[$text] = $value;
        }

        return [
            'valueToText' => $valueToText,
            'textToValue' => $textToValue,
        ];
    }

    public static function value2Text($value, array $options): ?string
    {
        $lookups = self::buildLookupArrays($options);
        return $lookups['valueToText'][$value] ?? null;
    }

    public static function text2Value(string $text, array $options): ?string
    {
        $lookups = self::buildLookupArrays($options);
        return $lookups['textToValue'][$text] ?? null;
    }

    public static function gDate($d)
    {
        if ($d) {
            return date('d.m.Y', strtotime($d));
        }

        return $d;
    }

    public static function datum($d)
    {
        return self::gDate($d);
    }

    public static function getLocale()
    {
        $browserLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en_US';

        if (class_exists('Locale')) {
            return Locale::acceptFromHttp($browserLang);
        }

        return 'en_US';
    }

    public static function convertSqlToLocal(array|object $row, array $dateFields, string $locale): array|object
    {
        static $formatters = [];

        $isArray = is_array($row);

        $get = static function ($row, string $field) use ($isArray) {
            if ($isArray) {
                return $row[$field] ?? null;
            }
            return $row->$field ?? null;
        };

        $set = static function (&$row, string $field, mixed $value) use ($isArray): void {
            if ($isArray) {
                $row[$field] = $value;
            } else {
                $row->$field = $value;
            }
        };

        foreach ($dateFields as $field => $type) {
            $value = $get($row, $field);

            if (empty($value)) {
                continue;
            }

            try {
                $isDateOnly = str_starts_with($type, 'date');
                $isTimeOnly = str_starts_with($type, 'time');
                $cacheKey = "{$locale}_{$isDateOnly}_{$isTimeOnly}";

                if (!isset($formatters[$cacheKey])) {
                    $dateStyle = $isTimeOnly ? IntlDateFormatter::NONE : IntlDateFormatter::SHORT;
                    $timeStyle = $isDateOnly ? IntlDateFormatter::NONE : IntlDateFormatter::SHORT;

                    $formatter = new IntlDateFormatter($locale, $dateStyle, $timeStyle);

                    if ($isDateOnly) {
                        $formatter->setPattern('dd.MM.yyyy');
                    } elseif ($isTimeOnly) {
                        $formatter->setPattern('HH:mm');
                    } else {
                        $formatter->setPattern('dd.MM.yyyy HH:mm');
                    }

                    $formatters[$cacheKey] = $formatter;
                }

                $dt = new DateTime($value);
                $set($row, $field, $formatters[$cacheKey]->format($dt));
            } catch (Exception) {
                continue;
            }
        }

        return $row;
    }

    public static function convertLocalToSql(array $row, array $dateFields, string $locale): array
    {
        static $formatters = [];

        foreach ($dateFields as $field => $type) {
            if (empty($row[$field])) {
                continue;
            }

            try {
                $isDateOnly = str_starts_with($type, 'date');
                $isTimeOnly = str_starts_with($type, 'time');
                $cacheKey = "{$locale}_{$isDateOnly}_{$isTimeOnly}";

                if (!isset($formatters[$cacheKey])) {
                    $dateStyle = $isTimeOnly ? IntlDateFormatter::NONE : IntlDateFormatter::SHORT;
                    $timeStyle = $isDateOnly ? IntlDateFormatter::NONE : IntlDateFormatter::SHORT;

                    $formatters[$cacheKey] = new IntlDateFormatter($locale, $dateStyle, $timeStyle);
                }

                $timestamp = $formatters[$cacheKey]->parse($row[$field]);

                if ($timestamp === false) {
                    continue;
                }

                $dt = new DateTime();
                $dt->setTimestamp($timestamp);

                $row[$field] = match (true) {
                    $isDateOnly => $dt->format('Y-m-d'),
                    $isTimeOnly => $dt->format('H:i:s'),
                    default => $dt->format('Y-m-d H:i:s'),
                };
            } catch (Exception) {
                continue;
            }
        }

        return $row;
    }

    public static function getInvalidDateFields(array $row, array $dateFields, string $locale): array
    {
        $invalidFields = [];
        static $formatters = [];

        foreach ($dateFields as $field => $type) {
            if (empty($row[$field])) {
                continue;
            }

            $isDateOnly = str_starts_with($type, 'date');
            $isTimeOnly = str_starts_with($type, 'time');
            $cacheKey = "{$locale}_{$isDateOnly}_{$isTimeOnly}";

            if (!isset($formatters[$cacheKey])) {
                $formatters[$cacheKey] = new IntlDateFormatter(
                    $locale,
                    $isTimeOnly ? IntlDateFormatter::NONE : IntlDateFormatter::SHORT,
                    $isDateOnly ? IntlDateFormatter::NONE : IntlDateFormatter::SHORT
                );
                $formatters[$cacheKey]->setLenient(false);
            }

            $pos = 0;
            $result = $formatters[$cacheKey]->parse($row[$field], $pos);

            if ($result === false || $pos !== strlen($row[$field])) {
                $invalidFields[] = $field;
            }
        }

        return $invalidFields;
    }

    public static function diffRecord(array|object $newRecord, array|object $oldRecord, array $ignoreFields = []): array
    {
        $new = is_object($newRecord) ? (array) $newRecord : $newRecord;
        $old = is_object($oldRecord) ? (array) $oldRecord : $oldRecord;

        $changes = [];

        foreach ($new as $field => $newValue) {
            if (in_array($field, $ignoreFields, true)) {
                continue;
            }

            if (!array_key_exists($field, $old)) {
                continue;
            }

            $oldValue = $old[$field];

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }
}
