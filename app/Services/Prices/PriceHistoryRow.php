<?php

namespace App\Services\Prices;

use App\Models\PriceItem;

/**
 * The cheapest offer of one pack size in each upload where the drug was on sale.
 */
final readonly class PriceHistoryRow
{
    /**
     * @param  int|null  $packCount  Null for products whose name gives no pack size.
     * @param  array<int, PriceItem>  $offersByUpload  Upload id => cheapest offer; missing when nobody sold it that week.
     */
    public function __construct(
        public ?int $packCount,
        public array $offersByUpload,
    ) {}
}
