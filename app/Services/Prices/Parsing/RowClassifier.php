<?php

namespace App\Services\Prices\Parsing;

use DateInterval;
use DateTimeInterface;

/**
 * Tells product rows from separators («Индия», «ABBOTT», «Фиксаторы шеи:»), discount lines and repeated headers:
 * a product row has a name with letters and a recognised price.
 */
final class RowClassifier
{
    public function __construct(private readonly PriceParser $prices = new PriceParser) {}

    /**
     * @param  list<bool|DateInterval|DateTimeInterface|float|int|string|null>  $values
     */
    public function classify(array $values, ColumnMapping $mapping): RowType
    {
        $name = $values[$mapping->nameCol] ?? null;

        if (! is_string($name) || preg_match('/\p{L}{2}/u', $name) !== 1) {
            return RowType::Text;
        }

        return $this->prices->parse($values[$mapping->priceCol] ?? null) === null ? RowType::Text : RowType::Product;
    }
}
