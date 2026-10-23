<?php

namespace App\Repositories;

use App\Models\Pharmacy;
use App\Models\PharmacySheetMapping;

class PharmacySheetMappingRepository
{
    /**
     * A pharmacy keeps one remembered mapping: choosing columns again replaces it.
     *
     * @param  array{header_row: int, name_col: int, price_col: int, price_discounted_col: int|null, header_signature: string}  $attributes
     */
    public function saveForPharmacy(Pharmacy $pharmacy, array $attributes): PharmacySheetMapping
    {
        return PharmacySheetMapping::query()->updateOrCreate(['pharmacy_id' => $pharmacy->id], $attributes);
    }
}
