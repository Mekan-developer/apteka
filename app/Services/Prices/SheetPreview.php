<?php

namespace App\Services\Prices;

use DateInterval;
use DateTimeInterface;

/**
 * One sheet of a price file: its name, how many non-empty rows it has
 * and the first of those rows exactly as they are stored in the file.
 */
final readonly class SheetPreview
{
    /**
     * @param  int  $index  Zero-based position of the sheet in the workbook.
     * @param  list<list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows
     */
    public function __construct(
        public int $index,
        public string $name,
        public int $rowCount,
        public array $rows,
    ) {}
}
