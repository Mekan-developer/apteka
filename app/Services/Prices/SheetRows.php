<?php

namespace App\Services\Prices;

use DateInterval;
use DateTimeInterface;

/**
 * All non-empty rows of one sheet, keyed by their row number in Excel (1-based).
 */
final readonly class SheetRows
{
    /**
     * @param  int  $index  Zero-based position of the sheet in the workbook.
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows
     */
    public function __construct(
        public int $index,
        public string $name,
        public array $rows,
    ) {}
}
