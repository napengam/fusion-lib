<?php
declare(strict_types=1);

use NumberFormatter;

class MoneyValidator
{
    private string $locale;
    private static array $cacheLocale = [];
    private NumberFormatter $formatter;
    private string $decimalSeparator;
    private string $thousandsSeparator;
    private string $currencySymbol;
    private int $symbolPosition;

    public function __construct(string $locale = 'de_DE')
    {
        $this->setLocale($locale);
    }

    public function setLocale(string $locale): void
    {
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

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function isValidMoneyAmount(string $amount): bool
    {
        $currency = null;
        $parsed = @$this->formatter->parseCurrency($amount, $currency);
        if ($parsed !== false) {
            return true;
        }

        $normalized = $this->normalizeNumberString($amount);
        return is_numeric($normalized);
    }

    public function parseMoneyAmount(string $amount): ?float
    {
        $currency = null;
        $parsed = @$this->formatter->parseCurrency($amount, $currency);
        if ($parsed !== false) {
            return (float)$parsed;
        }

        $normalized = $this->normalizeNumberString($amount);
        return is_numeric($normalized) ? (float)$normalized : null;
    }

    public function formatMoneyAmount(float $amount, bool $includeSymbol = true): string
    {
        $currencyCode = $this->formatter->getTextAttribute(NumberFormatter::CURRENCY_CODE) ?: 'EUR';

        return $includeSymbol
            ? $this->formatter->formatCurrency($amount, $currencyCode)
            : $this->formatter->format($amount);
    }

    private function normalizeNumberString(string $number): string
    {
        $ds = preg_quote($this->decimalSeparator, '/');
        $ts = preg_quote($this->thousandsSeparator, '/');
        $cleaned = preg_replace("/[^\d{$ds}{$ts}]/", '', $number);
        $cleaned = str_replace($this->thousandsSeparator, '', $cleaned);
        $cleaned = str_replace($this->decimalSeparator, '.', $cleaned);

        return $cleaned;
    }

    public function getCurrencySymbol(): string
    {
        return $this->currencySymbol;
    }

    public function getDecimalSeparator(): string
    {
        return $this->decimalSeparator;
    }

    public function getThousandsSeparator(): string
    {
        return $this->thousandsSeparator;
    }

    public function isSymbolPrefix(): bool
    {
        return $this->symbolPosition === 0;
    }

    public function getFormatter(): NumberFormatter
    {
        return $this->formatter;
    }

    public function parseMoneyAmountDetailed(string $amount): ?array
    {
        $currency = null;
        $parsed = @$this->formatter->parseCurrency($amount, $currency);
        if ($parsed !== false) {
            return ['value' => (float)$parsed, 'currency' => $currency];
        }
        return null;
    }
}
