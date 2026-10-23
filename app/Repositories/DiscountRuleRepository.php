<?php

namespace App\Repositories;

use App\Models\DiscountRule;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use Illuminate\Database\Eloquent\Collection;

class DiscountRuleRepository
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function insertMany(array $rows): void
    {
        if ($rows !== []) {
            DiscountRule::query()->insert($rows);
        }
    }

    public function deleteForUpload(PriceUpload $upload): void
    {
        DiscountRule::query()->whereBelongsTo($upload)->delete();
    }

    public function deleteForSheet(PriceUploadSheet $sheet): void
    {
        DiscountRule::query()
            ->where('price_upload_id', $sheet->price_upload_id)
            ->where('pharmacy_id', $sheet->pharmacy_id)
            ->delete();
    }

    /**
     * @return Collection<int, DiscountRule>
     */
    public function forSheet(PriceUploadSheet $sheet): Collection
    {
        return DiscountRule::query()
            ->where('price_upload_id', $sheet->price_upload_id)
            ->where('pharmacy_id', $sheet->pharmacy_id)
            ->orderBy('id')
            ->get();
    }
}
