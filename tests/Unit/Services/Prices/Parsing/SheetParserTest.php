<?php

use App\Enums\DiscountRuleType;
use App\Enums\PriceUploadSheetStatus;
use App\Services\Prices\Parsing\ColumnMapping;
use App\Services\Prices\Parsing\ParsedItem;
use App\Services\Prices\Parsing\ReviewReason;
use App\Services\Prices\Parsing\SheetDiscount;
use App\Services\Prices\Parsing\SheetParser;
use App\Services\Prices\Parsing\SheetParseResult;

function parseSheet(string $sheet): SheetParseResult
{
    return (new SheetParser)->parse($sheet, priceSheetRows($sheet));
}

/**
 * @return list<string>
 */
function itemNames(SheetParseResult $result): array
{
    return array_map(fn (ParsedItem $item): string => $item->rawName, $result->items);
}

/**
 * @return list<array{0: string, 1: string, 2: string|null}>
 */
function discountSummary(SheetParseResult $result): array
{
    return array_map(
        fn (SheetDiscount $discount): array => [$discount->type->value, $discount->percent, $discount->minSum],
        $result->discounts,
    );
}

test('parses every product row of a sheet with its row number, prices and goods discount', function () {
    $result = parseSheet('Kamat');
    [$alfagin] = $result->items;

    expect($result->status)->toBe(PriceUploadSheetStatus::Parsed)
        ->and(itemNames($result))->toBe([
            'Алфагин сироп 120мл.',
            'Верона капс. №20',
            'Инсти гранулы №5 бузина черная',
            'Азитромицин САНДОЗ 500мг таб. №3',
        ])
        ->and($alfagin->sourceRow)->toBe(6)
        ->and($alfagin->price)->toBe('43.10')
        ->and($alfagin->effectivePrice)->toBe('43.10')
        ->and($alfagin->goodsDiscount?->percent)->toBe('3.00')
        ->and($alfagin->manufacturer)->toBe('Пакистан')
        ->and($alfagin->name->baseName)->toBe('алфагин')
        ->and($alfagin->name->dose)->toBe('120мл');
});

test('the discounted price column becomes the price search sorts by', function () {
    [$altrikol] = parseSheet('ASDS')->items;

    expect([$altrikol->price, $altrikol->priceDiscounted, $altrikol->effectivePrice])->toBe(['320.00', '304.00', '304.00']);
});

test('the column label «Цена со скидкой -5%» is not a discount for the whole sheet', function () {
    expect(parseSheet('ASDS')->discounts)->toBe([]);
});

test('discounts from the order sum are kept but do not change the price', function () {
    $result = parseSheet('Ashgabat');

    expect(discountSummary($result))->toBe([
        ['price', '5.00', '10000.00'],
        ['price', '7.00', '30000.00'],
        ['price', '10.00', '50000.00'],
    ])
        ->and($result->items[0]->effectivePrice)->toBe('96.00');
});

test('rows without a price are not products', function () {
    expect(itemNames(parseSheet('Ashgabat')))->toBe(['Бетасерк 16мг таб №30', 'Бруфен Рапид 400мг капс №10']);
});

test('a discount ladder above the header gives one discount per step and skips the cashless surcharge', function () {
    $result = parseSheet('HayyrlyDerman');

    expect(discountSummary($result))->toBe([
        ['price', '2.00', '20000.00'],
        ['price', '3.00', '30000.00'],
        ['price', '4.00', '40000.00'],
        ['price', '5.00', '50000.00'],
    ])
        ->and($result->items[0]->manufacturer)->toBe('Мелиген, Россия');
});

test('manufacturer groups between products are skipped', function () {
    expect(itemNames(parseSheet('Gumry')))->toBe([
        'Бруфен Рапид 200мг капс №10 (Ибупрофен)',
        'Бруфен сироп 100мг/5мл 100мл (Ибупрофен)',
        'Гептрал 400мг/5мл пор для приг р-ра амп №5',
        'Бромгексин 4 Берлин-Хеми сироп 60мл',
        'Бромгексин 8 Берлин-Хеми драже №25',
    ]);
});

test('a goods discount line above the header is a discount for the whole sheet', function () {
    $result = parseSheet('Rysgally el');

    expect(discountSummary($result))->toBe([['goods', '5.00', null]])
        ->and($result->items)->toHaveCount(4)
        ->and($result->items[0]->effectivePrice)->toBe('32.00');
});

test('a sheet without a header is parsed from its data', function () {
    $result = parseSheet('Tebip apt');

    expect($result->status)->toBe(PriceUploadSheetStatus::Parsed)
        ->and($result->mapping?->headerRow)->toBeNull()
        ->and($result->items)->toHaveCount(6)
        ->and($result->items[0]->price)->toBe('55.00')
        ->and($result->items[0]->name->activeIngredient)->toBe('цефтириаксон');
});

test('prices written as text with a currency are read', function () {
    expect(array_map(fn (ParsedItem $item): string => $item->price, parseSheet('Em dem')->items))
        ->toBe(['5.00', '8.80', '0.48']);
});

test('the cash price is used when the bank transfer price is empty', function () {
    expect(array_map(fn (ParsedItem $item): string => $item->price, parseSheet('Enejan')->items))
        ->toBe(['55.00', '50.00', '45.00']);
});

test('a goods discount section title applies to the products below it only', function () {
    [$aurobin, $betaserk] = parseSheet('Batly Price-list')->items;

    expect($aurobin->goodsDiscount)->toBeNull()
        ->and($betaserk->goodsDiscount?->percent)->toBe('50.00')
        ->and(parseSheet('Batly Price-list')->discounts)->toBe([]);
});

test('a goods discount note next to a product belongs to that product', function () {
    $rows = [
        3 => ['№', 'Наименование', 'Производитель', 'Цена', 'Срок годности'],
        11 => [7, 'Доктор мом сироп 100мл ', 'Джонсон & Джонсон ООО', 85, '01.04.2029'],
        12 => [8, 'Ингавирин  30 мг /5 мл фл 50 мл  сироп', 'АВС Фармацойтичи', 175, '01.04.2029', 'нагрузка 10%'],
    ];

    [$doctorMom, $ingavirin] = (new SheetParser)->parse('Naz ada', $rows)->items;

    expect($doctorMom->goodsDiscount)->toBeNull()
        ->and($ingavirin->goodsDiscount?->percent)->toBe('10.00');
});

test('an unconditional price discount for the sheet lowers the price search sorts by', function () {
    $rows = [
        1 => ['ПРЕДОСТАВЛЯЕТСЯ СКИДКА -10%'],
        2 => ['№', 'Наименование', 'Цена'],
        3 => [1, 'Бетасерк 16мг таб №30', 96],
    ];

    $result = (new SheetParser)->parse('Rahmet', $rows);

    expect($result->discounts[0]->type)->toBe(DiscountRuleType::Price)
        ->and($result->items[0]->effectivePrice)->toBe('86.40');
});

test('a product repeated on the sheet is kept once', function () {
    $rows = [
        1 => ['Наименование', 'Цена'],
        2 => ['Димедрол 1% 1,0 №10', 13.9],
        3 => ['Димедрол 1% 1,0 №10 ', 13.9],
        4 => ['Димедрол 1% 1,0 №10', 8.5],
    ];

    expect((new SheetParser)->parse('Kamat', $rows)->items)->toHaveCount(2);
});

test('a sheet whose prices sit in another column for most rows needs review', function () {
    $result = parseSheet('Sirin Nabat');

    expect($result->status)->toBe(PriceUploadSheetStatus::NeedsReview)
        ->and($result->reviewReason)->toBe(ReviewReason::FewPrices)
        ->and($result->items)->toBe([]);
});

test('a sheet without name and price columns needs review', function () {
    $result = (new SheetParser)->parse('Notes', [1 => ['Прайс-лист'], 2 => ['Тел: +99362694637']]);

    expect($result->status)->toBe(PriceUploadSheetStatus::NeedsReview)
        ->and($result->reviewReason)->toBe(ReviewReason::ColumnsNotDetected);
});

test('a sheet whose columns hold no prices needs review', function () {
    $rows = [
        1 => ['№', 'Наименование', 'Цена'],
        2 => [14, 'Амоксиклав таб покр п/о 875мг', 'ожидаем'],
    ];

    expect((new SheetParser)->parse('Sagdyn Durmus', $rows)->reviewReason)->toBe(ReviewReason::NoProducts);
});

test('an empty sheet is skipped', function () {
    expect((new SheetParser)->parse('Пустой', [])->status)->toBe(PriceUploadSheetStatus::Skipped);
});

test('columns an admin chose are used instead of detection', function () {
    $rows = [
        1 => ['Товар', 'Стоимость'],
        2 => ['Бетасерк 16мг таб №30', 96],
    ];

    $result = (new SheetParser)->parse('Custom', $rows, new ColumnMapping(headerRow: 1, lastHeaderRow: 1, nameCol: 0, priceCol: 1));

    expect(itemNames($result))->toBe(['Бетасерк 16мг таб №30']);
});

test('the preview keeps the first raw rows with their row numbers in a form that fits JSON', function () {
    $preview = parseSheet('Kamat')->preview;

    expect($preview)->toHaveCount(9)
        ->and($preview[0]['row'])->toBe(1)
        ->and($preview[5])->toBe(['row' => 6, 'cells' => ['1.       ', 'Алфагин сироп 120мл.  ', 'Пакистан', '2028-02-01', 43.1, 42, 0.03]]);
});
