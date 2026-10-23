<?php

namespace App\Repositories;

use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use Illuminate\Database\Eloquent\Collection;

class PriceItemRepository
{
    private const int INSERT_CHUNK = 500;

    /**
     * Bulk insert: a sheet has up to ~2 000 products and one upload ~20 000.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function insertMany(array $rows): void
    {
        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            PriceItem::query()->insert($chunk);
        }
    }

    public function deleteForUpload(PriceUpload $upload): void
    {
        PriceItem::query()->whereBelongsTo($upload)->delete();
    }

    /**
     * A sheet's products are the upload's products of its pharmacy.
     */
    public function deleteForSheet(PriceUploadSheet $sheet): void
    {
        PriceItem::query()
            ->where('price_upload_id', $sheet->price_upload_id)
            ->where('pharmacy_id', $sheet->pharmacy_id)
            ->delete();
    }

    /**
     * The first products of a sheet in their order in the file, for the review screen.
     *
     * @return Collection<int, PriceItem>
     */
    public function firstForSheet(PriceUploadSheet $sheet, int $limit): Collection
    {
        return PriceItem::query()
            ->where('price_upload_id', $sheet->price_upload_id)
            ->where('pharmacy_id', $sheet->pharmacy_id)
            ->orderBy('source_row')
            ->limit($limit)
            ->get();
    }
}
