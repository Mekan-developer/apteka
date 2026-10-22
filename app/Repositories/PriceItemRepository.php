<?php

namespace App\Repositories;

use App\Models\PriceItem;
use App\Models\PriceUpload;

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
}
