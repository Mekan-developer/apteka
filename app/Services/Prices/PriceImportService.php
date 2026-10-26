<?php

namespace App\Services\Prices;

use App\Enums\PriceUploadSheetStatus;
use App\Enums\PriceUploadStatus;
use App\Models\Pharmacy;
use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use App\Repositories\DiscountRuleRepository;
use App\Repositories\PharmacyRepository;
use App\Repositories\PharmacySheetMappingRepository;
use App\Repositories\PriceItemRepository;
use App\Repositories\PriceUploadRepository;
use App\Repositories\PriceUploadSheetRepository;
use App\Services\Prices\Parsing\ColumnMapping;
use App\Services\Prices\Parsing\HeaderSignature;
use App\Services\Prices\Parsing\NormalizedName;
use App\Services\Prices\Parsing\ParsedItem;
use App\Services\Prices\Parsing\SheetDiscount;
use App\Services\Prices\Parsing\SheetParser;
use App\Services\Prices\Parsing\SheetParseResult;
use DateInterval;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
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
        private readonly PharmacySheetMappingRepository $mappings,
    ) {}

    /**
     * Safe to run again: whatever a previous attempt saved for this upload is replaced.
     * The upload ends up waiting for an admin to check it; activation is a separate step.
     */
    public function import(PriceUpload $upload): void
    {
        $path = $this->path($upload);
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
            $rows = $this->parser->visibleRows($sheet->rows, $sheet->merges);
            $result = $this->parser->parse($sheet->name, $rows, $this->savedMapping($pharmacy, $rows));

            DB::transaction(function () use ($upload, $pharmacy, $sheet, $result): void {
                $this->sheets->create([
                    'price_upload_id' => $upload->id,
                    'pharmacy_id' => $pharmacy->id,
                    'sheet_name' => $result->sheetName,
                    'sheet_index' => $sheet->index,
                    ...$this->sheetAttributes($result),
                ]);

                $this->storeProducts($upload, $pharmacy, $result);
                $this->pharmacies->updateContacts($pharmacy, $result->contacts);
            });

            $sheetsOk += $result->status === PriceUploadSheetStatus::Parsed ? 1 : 0;
            $rowsTotal += count($result->items);
        }

        $this->uploads->markImported($upload, $sheetsOk, $rowsTotal);
        $this->items->refreshStatistics();
    }

    /**
     * Parse one sheet again with the columns an admin chose. When that works, the columns are
     * remembered for the pharmacy and applied to its sheet in the next uploads.
     */
    public function reparseSheet(PriceUploadSheet $sheet, ColumnMapping $mapping): void
    {
        $sheet = $this->sheets->loadForReparse($sheet);
        $upload = $sheet->priceUpload;
        $pharmacy = $sheet->pharmacy;

        $sheetRows = $this->reader->sheet($this->path($upload), $sheet->sheet_index);
        $rows = $sheetRows === null ? [] : $this->parser->visibleRows($sheetRows->rows, $sheetRows->merges);
        $result = $this->parser->parse($sheet->sheet_name, $rows, $mapping);

        DB::transaction(function () use ($sheet, $upload, $pharmacy, $mapping, $rows, $result): void {
            $this->items->deleteForSheet($sheet);
            $this->discountRules->deleteForSheet($sheet);
            $this->sheets->update($sheet, $this->sheetAttributes($result));
            $this->storeProducts($upload, $pharmacy, $result);
            $this->pharmacies->updateContacts($pharmacy, $result->contacts);

            if ($result->status === PriceUploadSheetStatus::Parsed && $mapping->headerRow !== null) {
                $this->mappings->saveForPharmacy($pharmacy, [
                    'header_row' => $mapping->headerRow,
                    'name_col' => $mapping->nameCol,
                    'price_col' => $mapping->priceCol,
                    'price_discounted_col' => $mapping->priceDiscountedCol,
                    'header_signature' => HeaderSignature::of($rows[$mapping->headerRow] ?? []),
                ]);
            }

            $totals = $this->sheets->totalsForUpload($upload);
            $this->uploads->updateTotals($upload, $totals['sheetsOk'], $totals['rowsTotal']);
        });
    }

    /**
     * Bring an upload imported by an earlier version up to date without importing it again: its products get
     * the name fields search uses now (such as the dosage form) and, for the active upload, the pharmacies get
     * the contacts written in their sheets. Running it again gives the same result.
     */
    public function refresh(PriceUpload $upload): void
    {
        $this->items->chunkNamesForUpload($upload, 1000, function (Collection $items): void {
            $this->items->updateNames(array_map(fn (PriceItem $item): array => [
                'id' => $item->id,
                ...$this->nameAttributes($this->parser->normalizeProductName($item->raw_name)),
            ], array_values($items->all())));
        });

        $path = $this->path($upload);

        if ($upload->status === PriceUploadStatus::Active && is_file($path)) {
            foreach ($this->reader->sheets($path) as $sheet) {
                $pharmacy = $this->pharmacies->findOrCreateByName(trim($sheet->name));
                $rows = $this->parser->visibleRows($sheet->rows, $sheet->merges);

                $this->pharmacies->updateContacts($pharmacy, $this->parser->parse($sheet->name, $rows, $this->savedMapping($pharmacy, $rows))->contacts);
            }
        }

        $this->items->refreshStatistics();
    }

    public function markFailed(PriceUpload $upload): void
    {
        $this->uploads->markFailed($upload);
    }

    public function markSheetFailed(PriceUploadSheet $sheet, string $error): void
    {
        $this->sheets->markFailed($sheet, $error);
    }

    private function path(PriceUpload $upload): string
    {
        return Storage::disk(config('prices.disk'))->path($upload->stored_path);
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

    /**
     * @return array<string, mixed>
     */
    private function sheetAttributes(SheetParseResult $result): array
    {
        return [
            'status' => $result->status,
            'header_row' => $result->mapping?->headerRow,
            'name_col' => $result->mapping?->nameCol,
            'price_col' => $result->mapping?->priceCol,
            'price_discounted_col' => $result->mapping?->priceDiscountedCol,
            'rows_parsed' => count($result->items),
            'error' => $result->reviewReason?->value,
            'preview_json' => $result->preview,
        ];
    }

    /**
     * The columns search reads, derived from the product name.
     *
     * @return array{name_normalized: string, name_skeleton: string, base_name: string, name_compact: string, active_ingredient: string|null, pack_count: int|null, dose: string|null, dosage_form: string|null}
     */
    private function nameAttributes(NormalizedName $name): array
    {
        return [
            'name_normalized' => $name->normalized,
            'name_skeleton' => mb_substr($name->skeleton, 0, 255),
            'base_name' => $name->baseName,
            'name_compact' => str_replace(' ', '', $name->baseName),
            'active_ingredient' => $name->activeIngredient,
            'pack_count' => $name->packCount,
            'dose' => $name->dose === null ? null : mb_substr($name->dose, 0, 255),
            'dosage_form' => $name->dosageForm?->value,
        ];
    }

    private function storeProducts(PriceUpload $upload, Pharmacy $pharmacy, SheetParseResult $result): void
    {
        $this->items->insertMany(array_map(fn (ParsedItem $item): array => [
            'price_upload_id' => $upload->id,
            'pharmacy_id' => $pharmacy->id,
            'raw_name' => $item->rawName,
            ...$this->nameAttributes($item->name),
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
