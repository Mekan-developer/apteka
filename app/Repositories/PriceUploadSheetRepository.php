<?php

namespace App\Repositories;

use App\Enums\PriceUploadSheetStatus;
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PriceUploadSheet $sheet, array $attributes): void
    {
        $sheet->update($attributes);
    }

    public function deleteForUpload(PriceUpload $upload): void
    {
        PriceUploadSheet::query()->whereBelongsTo($upload)->delete();
    }

    /**
     * The sheet with the upload and pharmacy a reparse writes to.
     */
    public function loadForReparse(PriceUploadSheet $sheet): PriceUploadSheet
    {
        return $sheet->loadMissing(['priceUpload', 'pharmacy']);
    }

    public function markProcessing(PriceUploadSheet $sheet): void
    {
        $sheet->update(['status' => PriceUploadSheetStatus::Processing, 'error' => null]);
    }

    public function markFailed(PriceUploadSheet $sheet, string $error): void
    {
        $sheet->update(['status' => PriceUploadSheetStatus::Failed, 'error' => $error]);
    }

    public function hasProcessing(PriceUpload $upload): bool
    {
        return PriceUploadSheet::query()
            ->whereBelongsTo($upload)
            ->where('status', PriceUploadSheetStatus::Processing)
            ->exists();
    }

    /**
     * @return array{sheetsOk: int, rowsTotal: int}
     */
    public function totalsForUpload(PriceUpload $upload): array
    {
        $totals = PriceUploadSheet::query()
            ->whereBelongsTo($upload)
            ->selectRaw('count(*) filter (where status = ?) as sheets_ok', [PriceUploadSheetStatus::Parsed->value])
            ->selectRaw('coalesce(sum(rows_parsed), 0) as rows_total')
            ->toBase()
            ->first();

        return ['sheetsOk' => (int) ($totals->sheets_ok ?? 0), 'rowsTotal' => (int) ($totals->rows_total ?? 0)];
    }
}
