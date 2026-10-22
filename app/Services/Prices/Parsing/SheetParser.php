<?php

namespace App\Services\Prices\Parsing;

use App\Enums\DiscountRuleType;
use App\Enums\PriceUploadSheetStatus;
use DateInterval;
use DateTimeInterface;

/**
 * Turns the rows of one price sheet into products and discounts, or says why an admin has to look at it.
 */
final class SheetParser
{
    private const int PREVIEW_ROWS = 10;

    /** Below this share of priced rows the price column was most likely detected wrong. */
    private const float MIN_PRICED_SHARE = 0.5;

    /** A longer line with a goods discount inside the table is a product without a price, not a section title. */
    private const int MAX_SECTION_TITLE_LENGTH = 60;

    public function __construct(
        private readonly HeaderDetector $headers = new HeaderDetector,
        private readonly RowClassifier $rowClassifier = new RowClassifier,
        private readonly PriceParser $prices = new PriceParser,
        private readonly NameNormalizer $names = new NameNormalizer,
        private readonly DiscountExtractor $discounts = new DiscountExtractor,
        private readonly EffectivePriceCalculator $effectivePrices = new EffectivePriceCalculator,
    ) {}

    /**
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows  Non-empty rows keyed by Excel row number.
     * @param  ColumnMapping|null  $mapping  Columns an admin chose before; detected automatically when null.
     */
    public function parse(string $sheetName, array $rows, ?ColumnMapping $mapping = null): SheetParseResult
    {
        $preview = $this->preview($rows);

        if ($rows === []) {
            return new SheetParseResult($sheetName, PriceUploadSheetStatus::Skipped, null, [], [], null, $preview);
        }

        $mapping ??= $this->headers->detect($rows);

        if ($mapping === null) {
            return $this->needsReview($sheetName, null, ReviewReason::ColumnsNotDetected, $preview);
        }

        $drafts = [];
        $sheetDiscounts = [];
        $sectionDiscount = null;
        $unpricedRows = 0;

        foreach ($rows as $rowNumber => $values) {
            // Header labels such as «Цена со скидкой -5%» describe a column, not a discount for the sheet.
            if ($mapping->headerRow !== null && $rowNumber >= $mapping->headerRow && $rowNumber <= $mapping->lastHeaderRow) {
                continue;
            }

            // Lines above the table state discounts for the whole sheet.
            if ($rowNumber < $mapping->lastHeaderRow) {
                array_push($sheetDiscounts, ...$this->textDiscounts($values));

                continue;
            }

            if ($this->rowClassifier->classify($values, $mapping) === RowType::Product) {
                $draft = $this->draft($rowNumber, $values, $mapping, $sectionDiscount);
                $drafts[$draft['name']->normalized.'|'.$draft['price']] ??= $draft;

                continue;
            }

            if ($this->holdsNonPrice($values[$mapping->priceCol] ?? null)) {
                $unpricedRows++;
            }

            $sectionDiscount = null;

            foreach ($this->textDiscounts($values) as $discount) {
                if ($discount->type !== DiscountRuleType::Goods || $discount->conditional) {
                    $sheetDiscounts[] = $discount;
                } elseif (mb_strlen($discount->rawText) <= self::MAX_SECTION_TITLE_LENGTH) {
                    // «-50% товарная скидка» inside the table titles a section: it applies to the products below it.
                    $sectionDiscount = new ItemDiscount($discount->percent, $discount->rawText);
                }
            }
        }

        $sheetDiscounts = $this->unique($sheetDiscounts);
        $items = array_map(fn (array $draft): ParsedItem => new ParsedItem(
            sourceRow: $draft['row'],
            rawName: $draft['rawName'],
            name: $draft['name'],
            price: $draft['price'],
            priceDiscounted: $draft['priceDiscounted'],
            effectivePrice: $this->effectivePrices->calculate($draft['price'], $draft['priceDiscounted'], $sheetDiscounts),
            goodsDiscount: $draft['goodsDiscount'],
            manufacturer: $draft['manufacturer'],
        ), array_values($drafts));

        if ($items === []) {
            return $this->needsReview($sheetName, $mapping, ReviewReason::NoProducts, $preview, $sheetDiscounts);
        }

        if (count($items) / (count($items) + $unpricedRows) < self::MIN_PRICED_SHARE) {
            return $this->needsReview($sheetName, $mapping, ReviewReason::FewPrices, $preview, $sheetDiscounts);
        }

        return new SheetParseResult($sheetName, PriceUploadSheetStatus::Parsed, $mapping, $items, $sheetDiscounts, null, $preview);
    }

    /**
     * @param  list<bool|DateInterval|DateTimeInterface|float|int|string|null>  $values
     * @return list<SheetDiscount>
     */
    private function textDiscounts(array $values): array
    {
        $discounts = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                array_push($discounts, ...$this->discounts->fromSheetText($value));
            }
        }

        return $discounts;
    }

    /**
     * The goods discount of a product comes from, in order: the goods discount column, the product name,
     * a note in an unlabelled column («нагрузка 10%»), the section title above the product.
     *
     * @param  list<bool|DateInterval|DateTimeInterface|float|int|string|null>  $values
     * @return array{row: int, rawName: string, name: NormalizedName, price: string, priceDiscounted: string|null, goodsDiscount: ItemDiscount|null, manufacturer: string|null}
     */
    private function draft(int $rowNumber, array $values, ColumnMapping $mapping, ?ItemDiscount $sectionDiscount): array
    {
        $name = $values[$mapping->nameCol] ?? null;
        $rawName = $this->singleLine(is_string($name) ? $name : '');
        [$name, $nameDiscount] = $this->discounts->fromProductName($rawName);

        $goodsDiscount = $mapping->goodsDiscountCol === null
            ? null
            : $this->discounts->fromGoodsCell($values[$mapping->goodsDiscountCol] ?? null);

        $goodsDiscount ??= $nameDiscount ?? $this->noteDiscount($values, $mapping) ?? $sectionDiscount;

        $manufacturer = $mapping->manufacturerCol === null ? null : ($values[$mapping->manufacturerCol] ?? null);

        return [
            'row' => $rowNumber,
            'rawName' => $rawName,
            'name' => $this->names->normalize($name),
            'price' => (string) $this->prices->parse($values[$mapping->priceCol]),
            'priceDiscounted' => $mapping->priceDiscountedCol === null
                ? null
                : $this->prices->parse($values[$mapping->priceDiscountedCol] ?? null),
            'goodsDiscount' => $goodsDiscount,
            'manufacturer' => is_string($manufacturer) && trim($manufacturer) !== ''
                ? $this->singleLine(str_replace("\n", ', ', $manufacturer))
                : null,
        ];
    }

    /**
     * A value in the price column that is not a price: a date, «5%», «50/400». Many of them mean the price
     * column was detected wrong. Empty cells (separators) and words («ожидаем») are expected and do not count.
     */
    private function holdsNonPrice(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        return ! is_string($value) || (trim($value) !== '' && preg_match('/\p{L}/u', $value) !== 1);
    }

    /**
     * @param  list<bool|DateInterval|DateTimeInterface|float|int|string|null>  $values
     */
    private function noteDiscount(array $values, ColumnMapping $mapping): ?ItemDiscount
    {
        foreach ($values as $column => $value) {
            if (is_string($value) && ! in_array($column, [$mapping->nameCol, $mapping->manufacturerCol], true)) {
                $discount = $this->discounts->fromItemNote($value);

                if ($discount !== null) {
                    return $discount;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<SheetDiscount>  $discounts
     * @return list<SheetDiscount>
     */
    private function unique(array $discounts): array
    {
        $unique = [];

        foreach ($discounts as $discount) {
            $unique[$discount->type->value.'|'.$discount->percent.'|'.$discount->minSum.'|'.(int) $discount->conditional] ??= $discount;
        }

        return array_values($unique);
    }

    /**
     * @param  list<array{row: int, cells: list<bool|float|int|string|null>}>  $preview
     * @param  list<SheetDiscount>  $discounts
     */
    private function needsReview(string $sheetName, ?ColumnMapping $mapping, ReviewReason $reason, array $preview, array $discounts = []): SheetParseResult
    {
        return new SheetParseResult($sheetName, PriceUploadSheetStatus::NeedsReview, $mapping, [], $discounts, $reason, $preview);
    }

    /**
     * The first rows as JSON-safe values for the review screen.
     *
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows
     * @return list<array{row: int, cells: list<bool|float|int|string|null>}>
     */
    private function preview(array $rows): array
    {
        $preview = [];

        foreach (array_slice($rows, 0, self::PREVIEW_ROWS, true) as $rowNumber => $values) {
            $preview[] = [
                'row' => $rowNumber,
                'cells' => array_map(fn (bool|DateInterval|DateTimeInterface|float|int|string|null $value): bool|float|int|string|null => match (true) {
                    $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                    $value instanceof DateInterval => $value->format('%H:%I:%S'),
                    default => $value,
                }, $values),
            ];
        }

        return $preview;
    }

    private function singleLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{A0}", ' ', $text)));
    }
}
