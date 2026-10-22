<?php

use App\Services\Prices\Parsing\HeaderDetector;

/**
 * @return array{header: int|null, lastHeader: int, name: int, price: int, discounted: int|null, goods: int|null, manufacturer: int|null}|null
 */
function detectedColumns(array $rows): ?array
{
    $mapping = (new HeaderDetector)->detect($rows);

    return $mapping === null ? null : [
        'header' => $mapping->headerRow,
        'lastHeader' => $mapping->lastHeaderRow,
        'name' => $mapping->nameCol,
        'price' => $mapping->priceCol,
        'discounted' => $mapping->priceDiscountedCol,
        'goods' => $mapping->goodsDiscountCol,
        'manufacturer' => $mapping->manufacturerCol,
    ];
}

test('finds the columns of real price sheets', function (string $sheet, array $expected) {
    expect(detectedColumns(priceSheetRows($sheet)))->toBe($expected);
})->with([
    'two header rows and a goods discount column' => ['Kamat', ['header' => 4, 'lastHeader' => 5, 'name' => 1, 'price' => 4, 'discounted' => null, 'goods' => 6, 'manufacturer' => 2]],
    'main and discounted price' => ['ASDS', ['header' => 5, 'lastHeader' => 5, 'name' => 1, 'price' => 3, 'discounted' => 4, 'goods' => null, 'manufacturer' => 6]],
    'cash price besides bank transfer price' => ['Enejan', ['header' => 3, 'lastHeader' => 3, 'name' => 1, 'price' => 2, 'discounted' => null, 'goods' => null, 'manufacturer' => null]],
    'name column without a label' => ['Gumry', ['header' => 2, 'lastHeader' => 2, 'name' => 1, 'price' => 3, 'discounted' => null, 'goods' => null, 'manufacturer' => null]],
    'bilingual header' => ['Em dem', ['header' => 2, 'lastHeader' => 2, 'name' => 1, 'price' => 5, 'discounted' => null, 'goods' => null, 'manufacturer' => 3]],
    'no header at all' => ['Tebip apt', ['header' => null, 'lastHeader' => 0, 'name' => 2, 'price' => 3, 'discounted' => null, 'goods' => null, 'manufacturer' => null]],
    'header after discount lines' => ['Ashgabat', ['header' => 9, 'lastHeader' => 9, 'name' => 0, 'price' => 1, 'discounted' => null, 'goods' => null, 'manufacturer' => 2]],
    'section title below the header' => ['HayyrlyDerman', ['header' => 11, 'lastHeader' => 11, 'name' => 1, 'price' => 4, 'discounted' => null, 'goods' => null, 'manufacturer' => 3]],
    'name and price labels on two rows' => ['Rysgally el', ['header' => 5, 'lastHeader' => 6, 'name' => 0, 'price' => 1, 'discounted' => null, 'goods' => null, 'manufacturer' => null]],
]);

test('a single «НОВОЕ ПОСТУПЛЕНИЕ» row below the header is not a second header row', function () {
    expect(detectedColumns(priceSheetRows('Batly Price-list')))
        ->toMatchArray(['header' => 5, 'lastHeader' => 5, 'name' => 1, 'price' => 5]);
});

test('the name is read from the column with the text when its label sits over the row numbers', function () {
    $rows = [
        2 => [' НОВОЕ ПОСТУПЛЕНИЕ', '', 'Ед. изм.', 'Цена ТМ', 'Кол-во в кор(блок)', 'Страна и фирма производитель'],
        3 => [1, 'КОРТ-C (ГИДРОКОРТИЗОН) пор. д/приг р/ра', 'уп.', 13, 400, 'INDIA - NEON LABS'],
        4 => [2, 'ФЕЛИНИ ГЕЛ для наружного прим. тубе', 'уп.', 15, 200, 'INDIA'],
        5 => [3, 'ШИВАВИТ А, капсулы 100 мг №100', 'уп.', 75, 90, 'INDIA'],
    ];

    expect(detectedColumns($rows))->toMatchArray(['header' => 2, 'name' => 1, 'price' => 3]);
});

test('a «PRICE LIST» title and the row number column are never taken for the price', function () {
    $rows = [
        3 => [' PRICE LIST ', '', '', '', '99361033092  Огулджерен'],
        4 => [' По состоянию на 01.09.2026 г. '],
        7 => ['№ ', ' Наименование препарата ', 'Форма выпуска', 'Производитель', 'Цена  (манат) '],
        9 => [1, 'ПЕРВИСТОП   (PERVISTOP)    ', 'таблетки № 30', 'LO.LI. PHARMA International (ИТАЛИЯ)', 450],
        10 => [2, 'БИГВАНЕЛЕ (BIGUANELLE) ', 'вагинальный раствор во флаконе 100 мл', 'LO.LI. PHARMA International (ИТАЛИЯ)', 300],
        11 => [3, 'КСИМИНАЛ (XYMINAL) ', 'вагинальные суппозитории № 3 ', 'LO.LI. PHARMA International (ИТАЛИЯ)', 275],
        12 => [4, 'САНТЕС (SANTES) ', 'вагинальные суппозитории № 14', 'LO.LI. PHARMA International (ИТАЛИЯ)', 300],
    ];

    expect(detectedColumns($rows))->toMatchArray(['header' => 7, 'name' => 1, 'price' => 4]);
});

test('finds nothing on a sheet without names and prices', function () {
    expect((new HeaderDetector)->detect([1 => ['Прайс-лист'], 2 => ['Тел: +99362694637']]))->toBeNull();
});
