<?php

namespace App\Services\Prices\Parsing;

use App\Support\ExcelColumn;
use DateInterval;
use DateTimeInterface;

/**
 * Reads rows the way they look in Excel, where a merged range is one cell.
 *
 * Price files merge cells in part of a table only: «уп.» sits in D:E merged on the first rows and in D alone
 * further down, so the price is in F above and in E below. Dropping the empty cells a merge covers puts the
 * price of every row into the same column.
 */
final class MergedCells
{
    /**
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows  Non-empty rows keyed by Excel row number.
     * @param  list<string>  $merges  Merged ranges as Excel writes them, e.g. «D4:E4».
     * @return array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>
     */
    public static function collapse(array $rows, array $merges): array
    {
        $covered = [];

        foreach ($merges as $merge) {
            if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', strtoupper($merge), $match) !== 1) {
                continue;
            }

            $firstColumn = ExcelColumn::index($match[1]);
            $lastColumn = ExcelColumn::index($match[3]);

            for ($row = (int) $match[2]; $row <= (int) $match[4]; $row++) {
                for ($column = $firstColumn + 1; $column <= $lastColumn; $column++) {
                    $covered[$row][$column] = true;
                }
            }
        }

        foreach ($covered as $rowNumber => $columns) {
            if (isset($rows[$rowNumber])) {
                $rows[$rowNumber] = array_values(array_diff_key($rows[$rowNumber], $columns));
            }
        }

        return $rows;
    }
}
