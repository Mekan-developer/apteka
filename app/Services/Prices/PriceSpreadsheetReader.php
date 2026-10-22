<?php

namespace App\Services\Prices;

use DateInterval;
use DateTimeInterface;
use Generator;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\Sheet;

/**
 * Streams an .xlsx price file sheet by sheet.
 *
 * OpenSpout gives every sheet its own copy of the workbook styles and keeps all sheets until the
 * file is closed. Price files carry thousands of styles (~2 MB per sheet on the real file), so the
 * file is reopened every $sheetsPerOpen sheets to keep memory flat regardless of the sheet count.
 */
class PriceSpreadsheetReader
{
    public function __construct(private readonly int $sheetsPerOpen = 20) {}

    /**
     * Yield every sheet of the file with its non-empty row count and its first non-empty rows.
     *
     * @return Generator<int, SheetPreview>
     *
     * @throws OpenSpoutException When the file is missing or is not a readable .xlsx file.
     */
    public function previewSheets(string $path, int $previewRowCount = 8): Generator
    {
        foreach ($this->sheets($path) as $sheet) {
            yield new SheetPreview(
                $sheet->index,
                $sheet->name,
                count($sheet->rows),
                array_slice($sheet->rows, 0, $previewRowCount),
            );
        }
    }

    /**
     * Yield every sheet of the file with all its non-empty rows keyed by Excel row number.
     *
     * @return Generator<int, SheetRows>
     *
     * @throws OpenSpoutException When the file is missing or is not a readable .xlsx file.
     */
    public function sheets(string $path): Generator
    {
        $firstSheetIndex = 0;

        do {
            $hasMoreSheets = false;
            $reader = new Reader;
            $reader->open($path);

            try {
                foreach ($reader->getSheetIterator() as $sheet) {
                    if ($sheet->getIndex() < $firstSheetIndex) {
                        continue;
                    }

                    if ($sheet->getIndex() >= $firstSheetIndex + $this->sheetsPerOpen) {
                        $hasMoreSheets = true;

                        break;
                    }

                    yield $this->rows($sheet);
                }
            } finally {
                $reader->close();
                unset($reader);

                // The reader's objects reference each other, so only the cycle collector frees them.
                gc_collect_cycles();
            }

            $firstSheetIndex += $this->sheetsPerOpen;
        } while ($hasMoreSheets);
    }

    private function rows(Sheet $sheet): SheetRows
    {
        $rows = [];

        foreach ($sheet->getRowIterator() as $rowNumber => $row) {
            $values = $this->values($row);

            if ($values !== []) {
                $rows[$rowNumber] = $values;
            }
        }

        return new SheetRows($sheet->getIndex(), $sheet->getName(), $rows);
    }

    /**
     * The row values without trailing blank cells; an empty list means the whole row is blank.
     * Formula cells give the value Excel computed and saved, not the formula text.
     *
     * @return list<bool|DateInterval|DateTimeInterface|float|int|string|null>
     */
    private function values(Row $row): array
    {
        $values = array_values(array_map(
            fn (Cell $cell): bool|DateInterval|DateTimeInterface|float|int|string|null => $cell instanceof FormulaCell
                ? $cell->getComputedValue()
                : $cell->getValue(),
            $row->getCells(),
        ));

        while ($values !== [] && $this->isBlank($values[array_key_last($values)])) {
            array_pop($values);
        }

        return $values;
    }

    /**
     * Price files pad cells with spaces and non-breaking spaces, which count as blank.
     */
    private function isBlank(bool|DateInterval|DateTimeInterface|float|int|string|null $value): bool
    {
        return $value === null || (is_string($value) && preg_match('/^[\s\x{00A0}]*$/u', $value) === 1);
    }
}
