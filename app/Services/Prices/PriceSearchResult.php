<?php

namespace App\Services\Prices;

/**
 * Everything one search found: the variants of the drug with their cheapest pharmacies and a summary.
 */
final readonly class PriceSearchResult
{
    /**
     * @param  list<PriceSearchGroup>  $groups  Most relevant first, then the variants sold by more pharmacies.
     * @param  int  $pharmaciesCount  Pharmacies selling any of the variants.
     * @param  string|null  $lowestPrice  The lowest price over all variants, e.g. "1.10".
     * @param  string|null  $correctedTo  The drug name shown when the query was most likely misspelt.
     */
    public function __construct(
        public array $groups,
        public int $pharmaciesCount,
        public ?string $lowestPrice,
        public ?string $correctedTo,
    ) {}

    public static function empty(): self
    {
        return new self([], 0, null, null);
    }
}
