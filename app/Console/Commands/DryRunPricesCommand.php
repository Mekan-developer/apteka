<?php

namespace App\Console\Commands;

use App\Enums\PriceUploadSheetStatus;
use App\Services\Prices\Parsing\ColumnMapping;
use App\Services\Prices\Parsing\ParsedItem;
use App\Services\Prices\Parsing\SheetDiscount;
use App\Services\Prices\Parsing\SheetParser;
use App\Services\Prices\Parsing\SheetParseResult;
use App\Services\Prices\PriceSpreadsheetReader;
use App\Support\ExcelColumn;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use OpenSpout\Common\Exception\OpenSpoutException;

#[Signature('prices:dry-run {path : Path to the .xlsx price file} {--sheet= : Also list the parsed products of the sheet with this name}')]
#[Description('Parse every sheet of a price file without saving anything and report which sheets need review')]
class DryRunPricesCommand extends Command
{
    private const int SHOWN_ITEMS = 30;

    /**
     * Execute the console command.
     */
    public function handle(PriceSpreadsheetReader $reader, SheetParser $parser): int
    {
        $path = (string) $this->argument('path');
        $sheetToShow = $this->option('sheet');

        if (! is_file($path)) {
            $this->error(__('prices.file_not_found', ['path' => $path]));

            return self::FAILURE;
        }

        $startedAt = hrtime(true);
        $rows = [];
        $statusCounts = array_fill_keys(array_column(PriceUploadSheetStatus::cases(), 'value'), 0);
        $itemCount = 0;
        $shownSheet = null;

        try {
            foreach ($reader->sheets($path) as $sheet) {
                $result = $parser->parse($sheet->name, $sheet->rows);

                $statusCounts[$result->status->value]++;
                $itemCount += count($result->items);
                $rows[] = $this->summaryRow($sheet->index, $result);

                if (is_string($sheetToShow) && trim($sheet->name) === trim($sheetToShow)) {
                    $shownSheet = $result;
                }
            }
        } catch (OpenSpoutException $exception) {
            $this->error(__('prices.cannot_read', ['error' => $exception->getMessage()]));

            return self::FAILURE;
        }

        $this->table(array_values(__('prices.dry_run.headers')), $rows);

        if (is_string($sheetToShow)) {
            $this->showSheet($sheetToShow, $shownSheet);
        }

        $this->newLine();
        $this->info(__('prices.dry_run.summary', [
            'sheets' => count($rows),
            'parsed' => $statusCounts[PriceUploadSheetStatus::Parsed->value],
            'review' => $statusCounts[PriceUploadSheetStatus::NeedsReview->value],
            'skipped' => $statusCounts[PriceUploadSheetStatus::Skipped->value],
            'items' => $itemCount,
            'seconds' => number_format((hrtime(true) - $startedAt) / 1e9, 2),
            'memory' => number_format(memory_get_peak_usage(true) / 1024 / 1024, 1),
        ]));

        return self::SUCCESS;
    }

    /**
     * @return list<string|int>
     */
    private function summaryRow(int $index, SheetParseResult $result): array
    {
        $mapping = $result->mapping;

        return [
            ($index + 1).'. '.$result->sheetName,
            __('prices.sheet_statuses.'.$result->status->value),
            $mapping === null ? '' : $this->headerRows($mapping),
            $mapping === null ? '' : ExcelColumn::name($mapping->nameCol),
            $mapping === null ? '' : ExcelColumn::name($mapping->priceCol),
            $mapping?->priceDiscountedCol === null ? '' : ExcelColumn::name($mapping->priceDiscountedCol),
            count($result->items),
            implode(', ', array_map($this->discountLabel(...), $result->discounts)),
            $result->reviewReason === null ? '' : __('prices.review_reasons.'.$result->reviewReason->value),
        ];
    }

    private function showSheet(string $sheetName, ?SheetParseResult $result): void
    {
        $this->newLine();

        if ($result === null) {
            $this->error(__('prices.dry_run.sheet_not_found', ['name' => $sheetName]));

            return;
        }

        $this->table(
            array_values(__('prices.dry_run.item_headers')),
            array_map(fn (ParsedItem $item): array => [
                $item->sourceRow,
                Str::limit($item->rawName, 50),
                Str::limit($item->name->baseName, 30),
                $item->name->packCount ?? '',
                $item->name->dose ?? '',
                $item->priceDiscounted === null ? $item->price : "{$item->price} / {$item->priceDiscounted}",
                $item->effectivePrice,
                $item->goodsDiscount === null ? '' : ($item->goodsDiscount->percent ?? $item->goodsDiscount->note ?? ''),
            ], array_slice($result->items, 0, self::SHOWN_ITEMS)),
        );

        foreach ($result->discounts as $discount) {
            $this->line($this->discountLabel($discount).' — '.$discount->rawText);
        }
    }

    private function headerRows(ColumnMapping $mapping): string
    {
        return match (true) {
            $mapping->headerRow === null => __('prices.dry_run.no_header'),
            $mapping->headerRow === $mapping->lastHeaderRow => (string) $mapping->headerRow,
            default => "{$mapping->headerRow}–{$mapping->lastHeaderRow}",
        };
    }

    private function discountLabel(SheetDiscount $discount): string
    {
        $label = __('prices.discounts.'.$discount->type->value, ['percent' => (float) $discount->percent]);

        return match (true) {
            $discount->minSum !== null => __('prices.discounts.from_sum', [
                'discount' => $label,
                'sum' => number_format((float) $discount->minSum, 0, '.', ' '),
            ]),
            $discount->conditional => __('prices.discounts.conditional', ['discount' => $label]),
            default => $label,
        };
    }
}
