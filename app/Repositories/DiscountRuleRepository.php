<?php

namespace App\Repositories;

use App\Models\DiscountRule;
use App\Models\PriceUpload;

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
}
