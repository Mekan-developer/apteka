<?php

namespace App\Services\Prices\Parsing;

/**
 * One product row of a price sheet, ready to be stored as a price item.
 * Prices are decimal strings with two decimals, e.g. "2.50".
 */
final readonly class ParsedItem
{
    public function __construct(
        public int $sourceRow,
        public string $rawName,
        public NormalizedName $name,
        public string $price,
        public ?string $priceDiscounted,
        public string $effectivePrice,
        public ?ItemDiscount $goodsDiscount,
        public ?string $manufacturer,
    ) {}
}
