<?php

namespace App\Services\Prices\Parsing;

use DateInterval;
use DateTimeInterface;

/**
 * Finds the name and price columns of a sheet.
 *
 * The header is not the first row (1–6 rows of company name, phones and dates come first), it may span
 * two rows («Наименование» / « продукта»), and the name column is sometimes not labelled at all.
 * Sheets without any header are read from the data: the price column is the one whose cells state a currency.
 */
final class HeaderDetector
{
    private const int HEADER_SEARCH_ROWS = 20;

    private const int SAMPLE_ROWS = 60;

    /** Longer cells are titles and contacts («PRICE LIST», phones, e-mail), not column labels. */
    private const int MAX_LABEL_LENGTH = 80;

    /** Share of data rows that must hold text in the name column. */
    private const float MIN_NAME_TEXT_SHARE = 0.5;

    private const string PRICE_LABEL = '/(?<!\p{L})(?:цена|price(?!\s*list)|baha|manat|tmt)(?!\p{L})/u';

    private const string NAME_LABEL = '/наименован|номенклатур|описание|название|препарат|продукт|(?<!\p{L})ady(?!\p{L})/u';

    /** «НОВОЕ ПОСТУПЛЕНИЕ» sometimes labels the name column, but it also titles sections inside the table. */
    private const string NEW_ARRIVALS_LABEL = '/новое поступление/u';

    private const string DISCOUNTED_PRICE_LABEL = '/скидк/u';

    /** «Цена, переч.» is the price for a bank transfer; the main price is the cash one. */
    private const string IGNORED_PRICE_LABEL = '/переч|безнал/u';

    private const string GOODS_DISCOUNT_LABEL = '/скидк|нагрузк|бонус/u';

    private const string MANUFACTURER_LABEL = '/производит|фирма|страна/u';

    public function __construct(private readonly PriceParser $prices = new PriceParser) {}

    /**
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows  Excel row number => values.
     */
    public function detect(array $rows): ?ColumnMapping
    {
        return $this->detectFromHeader($rows) ?? $this->detectFromData($rows);
    }

    /**
     * Columns an admin chose cover only the name and prices: the goods discount and manufacturer
     * columns are still read from the header labels.
     *
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows
     */
    public function complete(array $rows, ColumnMapping $mapping): ColumnMapping
    {
        if ($mapping->headerRow === null || ! isset($rows[$mapping->headerRow])) {
            return $mapping;
        }

        $labels = $this->labels($rows[$mapping->headerRow]);
        $taken = [$mapping->nameCol, $mapping->priceCol, $mapping->priceDiscountedCol];

        return new ColumnMapping(
            headerRow: $mapping->headerRow,
            lastHeaderRow: $mapping->lastHeaderRow,
            nameCol: $mapping->nameCol,
            priceCol: $mapping->priceCol,
            priceDiscountedCol: $mapping->priceDiscountedCol,
            goodsDiscountCol: $mapping->goodsDiscountCol ?? $this->firstColumn($labels, self::GOODS_DISCOUNT_LABEL, except: $taken),
            manufacturerCol: $mapping->manufacturerCol ?? $this->firstColumn($labels, self::MANUFACTURER_LABEL, except: $taken),
        );
    }

    /**
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows
     */
    private function detectFromHeader(array $rows): ?ColumnMapping
    {
        $rowNumbers = array_keys($rows);

        foreach (array_slice($rowNumbers, 0, self::HEADER_SEARCH_ROWS) as $position => $rowNumber) {
            $labels = $this->labels($rows[$rowNumber]);

            if (count($labels) < 2 || ! $this->hasHeaderLabel($labels)) {
                continue;
            }

            $lastHeaderRow = $rowNumber;
            $nextRowNumber = $rowNumbers[$position + 1] ?? null;

            // A second header row has several labels; a single cell below the header is a section title.
            if ($nextRowNumber !== null && count($this->labels($rows[$nextRowNumber])) >= 2 && $this->isTextOnly($rows[$nextRowNumber])) {
                foreach ($this->labels($rows[$nextRowNumber]) as $column => $label) {
                    $labels[$column] = trim(($labels[$column] ?? '').' '.$label);
                }

                $lastHeaderRow = $nextRowNumber;
            }

            $mapping = $this->mappingFromLabels($labels, $rowNumber, $lastHeaderRow, $rows);

            if ($mapping !== null) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows
     */
    private function mappingFromLabels(array $labels, int $headerRow, int $lastHeaderRow, array $rows): ?ColumnMapping
    {
        $priceColumns = array_keys(array_filter($labels, fn (string $label): bool => preg_match(self::PRICE_LABEL, $label) === 1));

        if ($priceColumns === []) {
            return null;
        }

        $discountedColumn = $this->firstColumn($labels, self::DISCOUNTED_PRICE_LABEL, only: $priceColumns);
        $priceColumn = $this->firstColumn(
            array_filter($labels, fn (string $label): bool => preg_match(self::IGNORED_PRICE_LABEL, $label) !== 1),
            self::PRICE_LABEL,
            except: [$discountedColumn],
        );

        if ($priceColumn === null) {
            [$priceColumn, $discountedColumn] = [$discountedColumn, null];
        }

        if ($priceColumn === null) {
            return null;
        }

        $dataRows = array_slice(
            array_filter($rows, fn (int $rowNumber): bool => $rowNumber > $lastHeaderRow, ARRAY_FILTER_USE_KEY),
            0,
            self::SAMPLE_ROWS,
            true,
        );

        // The row number column («1, 2, 3…») is never the price, whatever its label says.
        if ($this->isSequential(array_map(fn (array $row): mixed => $row[$priceColumn] ?? null, $dataRows))) {
            return null;
        }

        // A label may sit over the row number column while the names are one column to the right.
        $nameColumn = $this->firstColumn($labels, self::NAME_LABEL, except: $priceColumns)
            ?? $this->firstColumn($labels, self::NEW_ARRIVALS_LABEL, except: $priceColumns);

        if ($nameColumn === null || $this->textShare($dataRows, $nameColumn) < self::MIN_NAME_TEXT_SHARE) {
            $nameColumn = $this->guessNameColumn($dataRows, $priceColumns);
        }

        if ($nameColumn === null) {
            return null;
        }

        $taken = [...$priceColumns, $nameColumn];

        return new ColumnMapping(
            headerRow: $headerRow,
            lastHeaderRow: $lastHeaderRow,
            nameCol: $nameColumn,
            priceCol: $priceColumn,
            priceDiscountedCol: $discountedColumn,
            goodsDiscountCol: $this->firstColumn($labels, self::GOODS_DISCOUNT_LABEL, except: $taken),
            manufacturerCol: $this->firstColumn($labels, self::MANUFACTURER_LABEL, except: $taken),
        );
    }

    /**
     * A sheet without a header: the price column holds amounts with a currency or with decimals,
     * the row number column («1, 2, 3…») is never taken for prices.
     *
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows
     */
    private function detectFromData(array $rows): ?ColumnMapping
    {
        $sample = array_slice($rows, 0, self::SAMPLE_ROWS, true);
        $width = max(array_map(count(...), $sample ?: [[]]));
        $priceColumn = null;
        $bestCount = 0;

        for ($column = 0; $column < $width; $column++) {
            $values = array_map(fn (array $row): mixed => $row[$column] ?? null, $sample);
            $prices = array_filter($values, fn (mixed $value): bool => $this->prices->parse($value) !== null);
            $marked = array_filter($prices, fn (mixed $value): bool => $this->prices->hasCurrency($value)
                || (is_float($value) && floor($value) !== $value));

            if (count($prices) > $bestCount
                && count($prices) >= count($sample) / 2
                && count($marked) >= count($prices) / 2
                && ! $this->isSequential($values)) {
                [$priceColumn, $bestCount] = [$column, count($prices)];
            }
        }

        if ($priceColumn === null) {
            return null;
        }

        $nameColumn = $this->guessNameColumn($sample, [$priceColumn]);

        return $nameColumn === null ? null : new ColumnMapping(null, 0, $nameColumn, $priceColumn);
    }

    /**
     * The column with the most text below the header.
     *
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows
     * @param  list<int|null>  $except
     */
    private function guessNameColumn(array $rows, array $except): ?int
    {
        $sample = array_slice($rows, 0, self::SAMPLE_ROWS, true);
        $textLengths = [];

        foreach ($sample as $row) {
            foreach ($row as $column => $value) {
                if (! in_array($column, $except, true) && is_string($value) && preg_match('/\p{L}{3}/u', $value) === 1) {
                    $textLengths[$column] = ($textLengths[$column] ?? 0) + mb_strlen(trim($value));
                }
            }
        }

        if ($textLengths === []) {
            return null;
        }

        arsort($textLengths);

        return array_key_first($textLengths);
    }

    /**
     * Share of rows whose cell in the column holds a word.
     *
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows
     */
    private function textShare(array $rows, int $column): float
    {
        if ($rows === []) {
            return 0.0;
        }

        $withText = array_filter($rows, fn (array $row): bool => is_string($row[$column] ?? null)
            && preg_match('/\p{L}{3}/u', (string) $row[$column]) === 1);

        return count($withText) / count($rows);
    }

    /**
     * Normalized non-empty header labels: lowercase, «Произ-водитель» → «производитель».
     *
     * @param  list<bool|DateInterval|DateTimeInterface|float|int|string|null>  $row
     * @return array<int, string>
     */
    private function labels(array $row): array
    {
        $labels = [];

        foreach ($row as $column => $value) {
            if (! is_string($value)) {
                continue;
            }

            $label = mb_strtolower(str_replace("\u{A0}", ' ', $value));
            $label = (string) preg_replace('/(?<=\p{L})-\s*(?=\p{L})/u', '', $label);
            $label = trim((string) preg_replace('/\s+/u', ' ', $label));

            if ($label !== '' && mb_strlen($label) <= self::MAX_LABEL_LENGTH) {
                $labels[$column] = $label;
            }
        }

        return $labels;
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function hasHeaderLabel(array $labels): bool
    {
        foreach ($labels as $label) {
            if (preg_match(self::PRICE_LABEL, $label) === 1
                || preg_match(self::NAME_LABEL, $label) === 1
                || preg_match(self::NEW_ARRIVALS_LABEL, $label) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The second header row holds only words: no numbers, dates or prices.
     *
     * @param  list<bool|DateInterval|DateTimeInterface|float|int|string|null>  $row
     */
    private function isTextOnly(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && ! is_string($value)) {
                return false;
            }

            if ($this->prices->parse($value) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $labels
     * @param  list<int>|null  $only
     * @param  list<int|null>  $except
     */
    private function firstColumn(array $labels, string $pattern, ?array $only = null, array $except = []): ?int
    {
        foreach ($labels as $column => $label) {
            if (($only === null || in_array($column, $only, true))
                && ! in_array($column, $except, true)
                && preg_match($pattern, $label) === 1) {
                return $column;
            }
        }

        return null;
    }

    /**
     * A row number column: most values are the previous one plus one.
     *
     * @param  array<int, mixed>  $values
     */
    private function isSequential(array $values): bool
    {
        $numbers = array_values(array_filter($values, fn (mixed $value): bool => is_int($value) || (is_float($value) && floor($value) === $value)));
        $steps = 0;

        for ($index = 1; $index < count($numbers); $index++) {
            if ($numbers[$index] == $numbers[$index - 1] + 1) {
                $steps++;
            }
        }

        return count($numbers) > 3 && $steps >= (count($numbers) - 1) * 0.7;
    }
}
