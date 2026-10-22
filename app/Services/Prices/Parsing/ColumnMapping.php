<?php

namespace App\Services\Prices\Parsing;

/**
 * Where the product name and prices are on a sheet.
 * Row numbers are as in Excel (1-based); column indexes are zero-based (A = 0).
 */
final readonly class ColumnMapping
{
    /**
     * @param  int|null  $headerRow  First header row; null when the columns were found from the data alone.
     * @param  int  $lastHeaderRow  Last header row (headers may span two rows); data starts below it.
     */
    public function __construct(
        public ?int $headerRow,
        public int $lastHeaderRow,
        public int $nameCol,
        public int $priceCol,
        public ?int $priceDiscountedCol = null,
        public ?int $goodsDiscountCol = null,
        public ?int $manufacturerCol = null,
    ) {}
}
