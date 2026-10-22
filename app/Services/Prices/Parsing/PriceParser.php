<?php

namespace App\Services\Prices\Parsing;

/**
 * Reads a price from a cell: 43.1, "96,00", "2,50 man.", "55,00m.", "0,48 teňňe.", "1 250,00".
 * Dates, quantities like "50/400" and anything else that is not a single amount give null.
 */
final class PriceParser
{
    private const float MAX_PRICE = 1_000_000;

    private const string AMOUNT = '/^(\d{1,3}(?:\s\d{3})+|\d+)(?:[.,](\d+))?\s*(?:manat|man|m|манат|ман|tmt|тмт|tm|тм|teňňe|т)?\.?$/u';

    /**
     * The price as a decimal string with two decimals, e.g. "2.50", or null when the cell holds no price.
     */
    public function parse(mixed $value): ?string
    {
        $amount = match (true) {
            is_int($value), is_float($value) => (float) $value,
            is_string($value) => $this->amountFromText($value),
            default => null,
        };

        if ($amount === null || $amount <= 0 || $amount >= self::MAX_PRICE) {
            return null;
        }

        return number_format(round($amount, 2), 2, '.', '');
    }

    /**
     * Whether the cell states its currency, as in "55,00m.": a strong sign of a price column without a header.
     */
    public function hasCurrency(mixed $value): bool
    {
        return is_string($value) && $this->parse($value) !== null && preg_match('/\p{L}/u', $value) === 1;
    }

    private function amountFromText(string $text): ?float
    {
        $text = mb_strtolower(trim(str_replace("\u{A0}", ' ', $text)));

        if (preg_match(self::AMOUNT, $text, $matches) !== 1) {
            return null;
        }

        return (float) (preg_replace('/\s/u', '', $matches[1]).'.'.($matches[2] ?? '0'));
    }
}
