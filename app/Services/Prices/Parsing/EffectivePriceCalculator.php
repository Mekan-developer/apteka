<?php

namespace App\Services\Prices\Parsing;

/**
 * The price search sorts by. Business rules from docs/price-search.md §3, kept in one place:
 * - a price from a discounted price column («Цена со скидкой -5%») is used as is;
 * - otherwise an unconditional price discount for the whole sheet lowers the price;
 * - goods discounts and discounts from an order sum never change it, they are shown as notes.
 */
final class EffectivePriceCalculator
{
    /**
     * @param  list<SheetDiscount>  $sheetDiscounts
     */
    public function calculate(string $price, ?string $priceDiscounted, array $sheetDiscounts): string
    {
        if ($priceDiscounted !== null) {
            return $priceDiscounted;
        }

        $percent = 0.0;

        foreach ($sheetDiscounts as $discount) {
            if ($discount->lowersPrice()) {
                $percent = max($percent, (float) $discount->percent);
            }
        }

        return number_format(round((float) $price * (100 - $percent) / 100, 2), 2, '.', '');
    }
}
