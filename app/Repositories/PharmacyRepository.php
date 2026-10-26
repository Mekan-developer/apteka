<?php

namespace App\Repositories;

use App\Models\Pharmacy;
use App\Services\Prices\Parsing\PharmacyContacts;

class PharmacyRepository
{
    /**
     * The pharmacy named after a sheet, created on its first upload, with its remembered column mapping.
     */
    public function findOrCreateByName(string $name): Pharmacy
    {
        return Pharmacy::query()->firstOrCreate(['name' => $name])->load('sheetMapping');
    }

    /**
     * The newest price sheet has the current contacts; a sheet without any keeps the ones known before.
     */
    public function updateContacts(Pharmacy $pharmacy, PharmacyContacts $contacts): void
    {
        if ($contacts->isEmpty()) {
            return;
        }

        $pharmacy->update([
            'phones' => $contacts->phones === [] ? $pharmacy->phones : $contacts->phones,
            'address' => $contacts->address ?? $pharmacy->address,
        ]);
    }
}
