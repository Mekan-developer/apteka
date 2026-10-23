<?php

namespace App\Services\Prices;

use App\Enums\PriceUploadSheetStatus;
use App\Enums\PriceUploadStatus;
use App\Jobs\ReparsePriceSheetJob;
use App\Models\DiscountRule;
use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use App\Repositories\DiscountRuleRepository;
use App\Repositories\PriceItemRepository;
use App\Repositories\PriceUploadSheetRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * The review screen of one sheet: what was parsed and parsing it again with chosen columns.
 */
class PriceSheetReviewService
{
    private const int SAMPLE_ITEMS = 20;

    public function __construct(
        private readonly PriceUploadSheetRepository $sheets,
        private readonly PriceItemRepository $items,
        private readonly DiscountRuleRepository $discountRules,
    ) {}

    /**
     * @return Collection<int, PriceItem>
     */
    public function sampleItems(PriceUploadSheet $sheet): Collection
    {
        return $this->items->firstForSheet($sheet, self::SAMPLE_ITEMS);
    }

    /**
     * @return Collection<int, DiscountRule>
     */
    public function discounts(PriceUploadSheet $sheet): Collection
    {
        return $this->discountRules->forSheet($sheet);
    }

    /**
     * Queue parsing the sheet again. Refused while the whole file is still being imported
     * or the sheet is already being parsed again.
     *
     * @param  array{header_row: int, name_col: int, price_col: int, price_discounted_col: int|null}  $columns
     */
    public function requestReparse(PriceUpload $upload, PriceUploadSheet $sheet, array $columns): bool
    {
        if (in_array($upload->status, [PriceUploadStatus::Uploaded, PriceUploadStatus::Processing], true)
            || $sheet->status === PriceUploadSheetStatus::Processing) {
            return false;
        }

        $this->sheets->markProcessing($sheet);

        ReparsePriceSheetJob::dispatch(
            $sheet,
            $columns['header_row'],
            $columns['name_col'],
            $columns['price_col'],
            $columns['price_discounted_col'],
        );

        return true;
    }
}
