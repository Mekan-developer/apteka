<?php

namespace App\Repositories;

use App\Models\Pharmacy;

class PharmacyRepository
{
    /**
     * The pharmacy named after a sheet, created on its first upload, with its remembered column mapping.
     */
    public function findOrCreateByName(string $name): Pharmacy
    {
        return Pharmacy::query()->firstOrCreate(['name' => $name])->load('sheetMapping');
    }
}
