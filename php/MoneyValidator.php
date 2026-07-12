<?php
/**
 * Class MoneyValidator
 *
 * WHAT:
 * Utility class for validating, parsing, and formatting monetary values
 * in a locale-aware and currency-safe way.
 *
 * Provides:
 * - Validation of currency strings and plain numeric inputs
 * - Parsing of formatted money values into float
 * - Formatting of floats into locale-specific currency strings
 * - Extraction of currency metadata (symbol, separators, position)
 * - Detailed parsing with currency code detection
 *
 * HOW:
 * - Uses PHP's NumberFormatter (Intl) for reliable locale-aware handling
 * - Detects decimal and thousands separators dynamically per locale
 * - Parses currency strings using parseCurrency()
 * - Falls back to normalized numeric parsing when no symbol is present
 * - Normalizes input by stripping invalid characters and unifying separators
 * - Caches NumberFormatter instances per locale for performance
 *
 * DESIGN:
 * - Locale-driven behavior (default: de_DE)
 * - Accepts flexible user input (e.g. "1.234,56 €", "1234.56", "€1,234.56")
 * - Safe parsing: returns null for invalid values instead of throwing
 * - Lightweight and reusable across CLI, APIs, and UI layers
 * - Keeps formatting and parsing consistent via a single formatter instance
 *
 * NOTES:
 * - Default currency is derived from locale (fallback: EUR)
 * - Decimal separator is normalized internally to "."
 * - Currency symbol position (prefix/suffix) is auto-detected
 * - NumberFormatter must be available (Intl extension required)
 *
 * USE CASES:
 * - Form validation for prices and amounts
 * - Converting user input into database-safe float values
 * - Displaying localized currency values in UI
 * - Handling multi-locale financial data
 */
declare(strict_types=1);

use NumberFormatter;

class MoneyValidator {

    private string $locale;
    private static array $cacheLocale = [];
    private NumberFormatter $formatter;
    private string $decimalSeparator;
    private string $thousandsSeparator;
    private string $currencySymbol;
    private int $symbolPosition;

    public function __construct(string $locale = 'de_DE') {
        $this->setLocale($locale);
    }

    public function setLocale(string $locale): void {
        $this->locale = $locale;

        if (!isset(self::$cacheLocale[$locale])) {
            self::$cacheLocale[$locale] = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        }

        $this->formatter = self::$cacheLocale[$locale];
        $this->decimalSeparator = $this->formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
        $this->thousandsSeparator = $this->formatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
        $this->currencySymbol = $this->formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);

        $pattern = $this->formatter->getPattern();
        $this->symbolPosition = strpos($pattern, '¤') < strpos($pattern, '#') ? 0 : 1;
    }

    public function getLocale(): string {
        return $this->locale;
    }

    public function isValidMoneyAmount(string $amount): bool {
        $currency = null;
        $parsed = $this->formatter->parseCurrency($amount, $currency);
        if ($parsed !== false) {
            return true;
        }

        $normalized = $this->normalizeNumberString($amount);
        return is_numeric($normalized);
    }

    public function parseMoneyAmount(string $amount): ?float {
        $currency = null;
        $parsed = $this->formatter->parseCurrency($amount, $currency);
        if ($parsed !== false) {
            return (float) $parsed;
        }

        $normalized = $this->normalizeNumberString($amount);
        return is_numeric($normalized) ? (float) $normalized : null;
    }

    public function formatMoneyAmount(float $amount, bool $includeSymbol = true): string {
        $currencyCode = $this->formatter->getTextAttribute(NumberFormatter::CURRENCY_CODE) ?: 'EUR';

        return $includeSymbol ? $this->formatter->formatCurrency($amount, $currencyCode) : $this->formatter->format($amount);
    }

    private function normalizeNumberString(string $number): string {
        $ds = preg_quote($this->decimalSeparator, '/');
        $ts = preg_quote($this->thousandsSeparator, '/');
        $cleaned = preg_replace("/[^\d{$ds}{$ts}]/", '', $number);
        $cleaned = str_replace($this->thousandsSeparator, '', $cleaned);
        $cleaned = str_replace($this->decimalSeparator, '.', $cleaned);

        return $cleaned;
    }

    public function getCurrencySymbol(): string {
        return $this->currencySymbol;
    }

    public function getDecimalSeparator(): string {
        return $this->decimalSeparator;
    }

    public function getThousandsSeparator(): string {
        return $this->thousandsSeparator;
    }

    public function isSymbolPrefix(): bool {
        return $this->symbolPosition === 0;
    }

    public function getFormatter(): NumberFormatter {
        return $this->formatter;
    }

    public function parseMoneyAmountDetailed(string $amount): ?array {
        $currency = null;
        $parsed = $this->formatter->parseCurrency($amount, $currency);
        if ($parsed !== false) {
            return ['value' => (float) $parsed, 'currency' => $currency];
        }
        return null;
    }
}
