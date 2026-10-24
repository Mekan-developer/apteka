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

    /**
     * Zero-based index of an Excel column name: A → 0, Z → 25, AA → 26.
     */
    public static function index(string $name): int
    {
        $number = 0;

        foreach (str_split(strtoupper($name)) as $letter) {
            $number = $number * 26 + ord($letter) - ord('A') + 1;
        }

        return $number - 1;
    }
}
