<?php

namespace App\Support;

final class ExcelColumn
{
    /**
     * Excel column name for a zero-based index: 0 → A, 25 → Z, 26 → AA.
     */
    public static function name(int $index): string
    {
        $name = '';

        for ($number = $index + 1; $number > 0; $number = intdiv($number - 1, 26)) {
            $name = chr(ord('A') + ($number - 1) % 26).$name;
        }

        return $name;
    }
}
