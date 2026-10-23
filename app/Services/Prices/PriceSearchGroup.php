<?php

namespace App\Services\Prices;

use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * The cheapest pharmacies for one pack size: prices are only comparable within «№N».
 */
final readonly class PriceSearchGroup
{
    /**
     * @param  int|null  $packCount  Null for products whose name gives no pack size.
     * @param  Collection<int, PriceItem>  $offers  Cheapest first; one offer per pharmacy.
     */
    public function __construct(
        public ?int $packCount,
        public Collection $offers,
    ) {}
}
