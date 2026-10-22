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

                    yield $this->preview($sheet, $previewRowCount);
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

    private function preview(Sheet $sheet, int $previewRowCount): SheetPreview
    {
        $rowCount = 0;
        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $values = $this->values($row);

            if ($values === []) {
                continue;
            }

            $rowCount++;

            if (count($rows) < $previewRowCount) {
                $rows[] = $values;
            }
        }

        return new SheetPreview($sheet->getIndex(), $sheet->getName(), $rowCount, $rows);
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
