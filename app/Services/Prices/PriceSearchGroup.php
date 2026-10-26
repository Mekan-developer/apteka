<?php

namespace App\Services\Prices;

use App\Enums\DosageForm;
use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * The cheapest pharmacies for one variant of a drug: prices are only comparable within one dosage form,
 * dose and pack size («Азитромицин · 500 мг · таблетки · №3»).
 */
final readonly class PriceSearchGroup
{
    /**
     * @param  string  $drugName  The words the products' base names share, e.g. «азитромицин».
     * @param  string|null  $dose  As normalized on import, e.g. «200мг 5мл»; null when the names give none.
     * @param  int|null  $packCount  Null for products whose name gives no pack size.
     * @param  Collection<int, PriceItem>  $offers  Cheapest first, one offer per pharmacy; each carries its "place"
     *                                              and "price_difference" (how much dearer than the first place).
     * @param  int  $pharmaciesCount  How many pharmacies sell the variant, also beyond the shown offers.
     * @param  float  $relevance  How closely the best product of the variant matches the query.
     */
    public function __construct(
        public string $drugName,
        public ?DosageForm $dosageForm,
        public ?string $dose,
        public ?int $packCount,
        public Collection $offers,
        public int $pharmaciesCount,
        public float $relevance,
    ) {}

    /**
     * Identifies the variant within one search, e.g. for collapsed sections on the page.
     */
    public function key(): string
    {
        return implode('|', [$this->dosageForm->value ?? '', $this->dose ?? '', $this->packCount ?? '']);
    }
}
