<?php

namespace App\Services\Prices;

use App\Models\PriceUpload;
use Illuminate\Database\Eloquent\Collection;

/**
 * How the cheapest price of a drug changed over the last published uploads.
 */
final readonly class PriceHistory
{
    /**
     * @param  Collection<int, PriceUpload>  $uploads  Newest first: the columns of the history.
     * @param  list<PriceHistoryRow>  $rows  One row per pack size.
     */
    public function __construct(
        public Collection $uploads,
        public array $rows,
    ) {}
}
