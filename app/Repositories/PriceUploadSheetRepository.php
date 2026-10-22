<?php

namespace App\Repositories;

use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;

class PriceUploadSheetRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PriceUploadSheet
    {
        return PriceUploadSheet::query()->create($attributes);
    }

    public function deleteForUpload(PriceUpload $upload): void
    {
        PriceUploadSheet::query()->whereBelongsTo($upload)->delete();
    }
}
