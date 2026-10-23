<?php

namespace App\Repositories;

use App\Enums\PriceUploadStatus;
use App\Models\PriceUpload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PriceUploadRepository
{
    /**
     * The upload search uses; the database allows only one.
     */
    public function findActive(): ?PriceUpload
    {
        return PriceUpload::query()->where('status', PriceUploadStatus::Active)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PriceUpload
    {
        return PriceUpload::query()->create($attributes);
    }

    /**
     * Newest uploads first, with who uploaded them and how many sheets are already processed.
     *
     * @return LengthAwarePaginator<int, PriceUpload>
     */
    public function paginateForAdmin(int $perPage = 20): LengthAwarePaginator
    {
        return PriceUpload::query()
            ->with('user:id,name')
            ->withCount('sheets')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * The upload with its sheets in workbook order, for the progress and review screen.
     */
    public function loadWithSheets(PriceUpload $upload): PriceUpload
    {
        return $upload
            ->load(['user:id,name', 'sheets' => fn ($query) => $query->orderBy('sheet_index')])
            ->loadCount('sheets');
    }

    public function markProcessing(PriceUpload $upload, int $sheetsTotal): void
    {
        $upload->update([
            'status' => PriceUploadStatus::Processing,
            'sheets_total' => $sheetsTotal,
            'sheets_ok' => 0,
            'rows_total' => 0,
        ]);
    }

    public function markImported(PriceUpload $upload, int $sheetsOk, int $rowsTotal): void
    {
        $upload->update([
            'status' => PriceUploadStatus::NeedsReview,
            'sheets_ok' => $sheetsOk,
            'rows_total' => $rowsTotal,
        ]);
    }

    public function markFailed(PriceUpload $upload): void
    {
        $upload->update(['status' => PriceUploadStatus::Failed]);
    }

    public function updateTotals(PriceUpload $upload, int $sheetsOk, int $rowsTotal): void
    {
        $upload->update(['sheets_ok' => $sheetsOk, 'rows_total' => $rowsTotal]);
    }

    /**
     * The upload search used so far becomes history.
     */
    public function archiveActive(): void
    {
        PriceUpload::query()
            ->where('status', PriceUploadStatus::Active)
            ->update(['status' => PriceUploadStatus::Archived]);
    }

    public function activate(PriceUpload $upload): void
    {
        $upload->update(['status' => PriceUploadStatus::Active, 'activated_at' => now()]);
    }
}
