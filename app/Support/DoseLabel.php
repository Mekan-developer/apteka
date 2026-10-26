<?php

namespace App\Support;

/**
 * A dose as normalized on import («200мг 5мл 20мл») written for the visitor in their language: «200 мг, 5 мл, 20 мл».
 */
final class DoseLabel
{
    public static function format(?string $dose): ?string
    {
        if ($dose === null || $dose === '') {
            return null;
        }

        return implode(', ', array_map(function (string $part): string {
            if (preg_match('/^(\d+(?:\.\d+)?)(\D+)$/u', $part, $matches) !== 1) {
                return $part;
            }

            $unit = __("prices.units.{$matches[2]}");
            $number = str_replace('.', ',', $matches[1]);

            return $matches[2] === '%' ? $number.$unit : "{$number} {$unit}";
        }, explode(' ', $dose)));
    }
}
