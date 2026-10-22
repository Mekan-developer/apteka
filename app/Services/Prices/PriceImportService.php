<?php

namespace App\Services\Prices;

use App\Enums\PriceUploadSheetStatus;
use App\Models\Pharmacy;
use App\Models\PriceUpload;
use App\Repositories\DiscountRuleRepository;
use App\Repositories\PharmacyRepository;
use App\Repositories\PriceItemRepository;
use App\Repositories\PriceUploadRepository;
use App\Repositories\PriceUploadSheetRepository;
use App\Services\Prices\Parsing\ColumnMapping;
use App\Services\Prices\Parsing\HeaderSignature;
use App\Services\Prices\Parsing\ParsedItem;
use App\Services\Prices\Parsing\SheetDiscount;
use App\Services\Prices\Parsing\SheetParser;
use App\Services\Prices\Parsing\SheetParseResult;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reads a stored price file sheet by sheet and saves the products and discounts of every pharmacy.
 */
class PriceImportService
{
    public function __construct(
        private readonly PriceSpreadsheetReader $reader,
        private readonly SheetParser $parser,
        private readonly PriceUploadRepository $uploads,
        private readonly PharmacyRepository $pharmacies,
        private readonly PriceUploadSheetRepository $sheets,
        private readonly PriceItemRepository $items,
        private readonly DiscountRuleRepository $discountRules,
    ) {}

    /**
     * Safe to run again: whatever a previous attempt saved for this upload is replaced.
     * The upload ends up waiting for an admin to check it; activation is a separate step.
     */
    public function import(PriceUpload $upload): void
    {
        $path = Storage::disk(config('prices.disk'))->path($upload->stored_path);
        $sheetsTotal = count($this->reader->sheetNames($path));

        DB::transaction(function () use ($upload, $sheetsTotal): void {
            $this->items->deleteForUpload($upload);
            $this->discountRules->deleteForUpload($upload);
            $this->sheets->deleteForUpload($upload);
            $this->uploads->markProcessing($upload, $sheetsTotal);
        });

        $sheetsOk = 0;
        $rowsTotal = 0;

        foreach ($this->reader->sheets($path) as $sheet) {
            $pharmacy = $this->pharmacies->findOrCreateByName(trim($sheet->name));
            $result = $this->parser->parse($sheet->name, $sheet->rows, $this->savedMapping($pharmacy, $sheet->rows));

            DB::transaction(fn () => $this->store($upload, $pharmacy, $sheet->index, $result));

            $sheetsOk += $result->status === PriceUploadSheetStatus::Parsed ? 1 : 0;
            $rowsTotal += count($result->items);
        }

        $this->uploads->markImported($upload, $sheetsOk, $rowsTotal);
    }

    public function markFailed(PriceUpload $upload): void
    {
        $this->uploads->markFailed($upload);
    }

    /**
     * The columns an admin chose for this pharmacy, as long as its header has not changed since.
     *
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows
     */
    private function savedMapping(Pharmacy $pharmacy, array $rows): ?ColumnMapping
    {
        $saved = $pharmacy->sheetMapping;

        if ($saved === null
            || ! isset($rows[$saved->header_row])
            || HeaderSignature::of($rows[$saved->header_row]) !== $saved->header_signature) {
            return null;
        }

        return new ColumnMapping(
            headerRow: $saved->header_row,
            lastHeaderRow: $saved->header_row,
            nameCol: $saved->name_col,
            priceCol: $saved->price_col,
            priceDiscountedCol: $saved->price_discounted_col,
        );
    }

    private function store(PriceUpload $upload, Pharmacy $pharmacy, int $sheetIndex, SheetParseResult $result): void
    {
        $this->sheets->create([
            'price_upload_id' => $upload->id,
            'pharmacy_id' => $pharmacy->id,
            'sheet_name' => $result->sheetName,
            'sheet_index' => $sheetIndex,
            'status' => $result->status,
            'header_row' => $result->mapping?->headerRow,
            'name_col' => $result->mapping?->nameCol,
            'price_col' => $result->mapping?->priceCol,
            'price_discounted_col' => $result->mapping?->priceDiscountedCol,
            'rows_parsed' => count($result->items),
            'error' => $result->reviewReason?->value,
            'preview_json' => $result->preview,
        ]);

        $this->items->insertMany(array_map(fn (ParsedItem $item): array => [
            'price_upload_id' => $upload->id,
            'pharmacy_id' => $pharmacy->id,
            'raw_name' => $item->rawName,
            'name_normalized' => $item->name->normalized,
            'name_skeleton' => mb_substr($item->name->skeleton, 0, 255),
            'base_name' => $item->name->baseName,
            'active_ingredient' => $item->name->activeIngredient,
            'pack_count' => $item->name->packCount,
            'dose' => $item->name->dose === null ? null : mb_substr($item->name->dose, 0, 255),
            'price' => $item->price,
            'price_discounted' => $item->priceDiscounted,
            'effective_price' => $item->effectivePrice,
            'goods_discount_percent' => $item->goodsDiscount?->percent,
            'discount_note' => $item->goodsDiscount?->note,
            'manufacturer' => $item->manufacturer === null ? null : mb_substr($item->manufacturer, 0, 255),
            'source_row' => $item->sourceRow,
        ], $result->items));

        $this->discountRules->insertMany(array_map(fn (SheetDiscount $discount): array => [
            'price_upload_id' => $upload->id,
            'pharmacy_id' => $pharmacy->id,
            'type' => $discount->type->value,
            'percent' => $discount->percent,
            'min_sum' => $discount->minSum,
            'is_conditional' => $discount->conditional,
            'raw_text' => $discount->rawText,
        ], $result->discounts));
    }
}
