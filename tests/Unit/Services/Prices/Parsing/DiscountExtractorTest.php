<?php

use App\Enums\DiscountRuleType;
use App\Services\Prices\Parsing\DiscountExtractor;
use App\Services\Prices\Parsing\SheetDiscount;

/**
 * @return list<array{type: string, percent: string, minSum: string|null, conditional: bool}>
 */
function sheetDiscounts(string $text): array
{
    return array_map(fn (SheetDiscount $discount): array => [
        'type' => $discount->type->value,
        'percent' => $discount->percent,
        'minSum' => $discount->minSum,
        'conditional' => $discount->conditional,
    ], (new DiscountExtractor)->fromSheetText($text));
}

test('reads a discount stated for the whole sheet', function (string $text, string $type, string $percent, bool $conditional) {
    expect(sheetDiscounts($text))->toBe([['type' => $type, 'percent' => $percent, 'minSum' => null, 'conditional' => $conditional]]);
})->with([
    'goods discount' => ['Товарная скидка 5%', 'goods', '5.00', false],
    'goods load with a plus' => [' Ораз 865-54-74-95 Нагрузка + 3%', 'goods', '3.00', false],
    'price discount' => ['-5% Скидка!', 'price', '5.00', false],
    'price discount for cash payment' => ['"5%" скидка при оплате наличными', 'price', '5.00', true],
    'price discount for a number of boxes' => ['Скидка 5% при покупке 5 коробок', 'price', '5.00', true],
]);

test('reads a discount that depends on the order sum', function (string $text, string $type, string $minSum) {
    expect(sheetDiscounts($text))->toBe([['type' => $type, 'percent' => '5.00', 'minSum' => $minSum, 'conditional' => true]]);
})->with([
    'sum in thousands' => ['От суммы выше 10тыс манат финансовая скидка 5% на все', 'price', '10000.00'],
    'goods discount from a sum' => ['ПРИ ЗАКАЗЕ НА СУММУ ОТ 10000 МАНАТ- 5% ТОВАРНАЯ СКИДКА', 'goods', '10000.00'],
    'sum with a space' => ['50 000 ман -5% скидка', 'price', '50000.00'],
]);

test('reads every step of a discount ladder in one line', function () {
    expect(sheetDiscounts('20 000 ман -2% скидка / 30 000 ман -3% скидка'))->toBe([
        ['type' => 'price', 'percent' => '2.00', 'minSum' => '20000.00', 'conditional' => true],
        ['type' => 'price', 'percent' => '3.00', 'minSum' => '30000.00', 'conditional' => true],
    ]);
});

test('finds no discount in lines that only look like one', function (string $text) {
    expect(sheetDiscounts($text))->toBe([]);
})->with([
    'surcharge for cashless payment' => ['При безналичном расчете +10 %'],
    'no goods load' => ['НАГРУЗКИ НЕТ'],
    'minimum order' => ['Минимальная сумма заказа - 2000'],
    'promotion with a bonus' => ['Амоксиклав пор для приг р-р 1000мг/200мг №10+бонус 10 манат+подарок 1 уп Тиогамма 1/2 % 50мл'],
]);

test('reads a goods discount column cell', function (mixed $value, ?string $percent) {
    expect((new DiscountExtractor)->fromGoodsCell($value)?->percent)->toBe($percent);
})->with([
    'share' => [0.03, '3.00'],
    'text with a percent' => ['10% товарный скидка', '10.00'],
    'no discount' => ['без скидки', null],
    'zero' => [0, null],
    'empty' => [null, null],
]);

test('takes a goods discount out of a product name', function (string $name, string $cleanName, ?string $percent, string $note) {
    [$withoutDiscount, $discount] = (new DiscountExtractor)->fromProductName($name);

    expect($withoutDiscount)->toBe($cleanName)
        ->and($discount?->percent)->toBe($percent)
        ->and($discount?->note)->toBe($note);
})->with([
    'without a percent' => ['Акбар №100 (+ товар.скидка)', 'Акбар №100', null, '(+ товар.скидка)'],
    'with a percent' => ['Кокарбоксилаза 50 мг №5 15% Товарная скидка', 'Кокарбоксилаза 50 мг №5', '15.00', '15% Товарная скидка'],
]);

test('reads a goods discount note next to a product', function () {
    $discount = (new DiscountExtractor)->fromItemNote('нагрузка 10%');

    expect($discount?->percent)->toBe('10.00')
        ->and($discount?->note)->toBe('нагрузка 10%');
});

test('a price discount note next to a product is not a goods discount', function () {
    expect((new DiscountExtractor)->fromItemNote('-5% Скидка!'))->toBeNull();
});

test('only an unconditional price discount lowers the price', function () {
    [$price] = (new DiscountExtractor)->fromSheetText('-5% Скидка!');
    [$goods] = (new DiscountExtractor)->fromSheetText('Товарная скидка 5%');
    [$cash] = (new DiscountExtractor)->fromSheetText('"5%" скидка при оплате наличными');

    expect([$price->lowersPrice(), $goods->lowersPrice(), $cash->lowersPrice()])->toBe([true, false, false])
        ->and($price->type)->toBe(DiscountRuleType::Price);
});
