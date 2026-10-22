<?php

use App\Enums\DiscountRuleType;
use App\Services\Prices\Parsing\EffectivePriceCalculator;
use App\Services\Prices\Parsing\SheetDiscount;

test('an unconditional price discount for the sheet lowers the price', function () {
    $discount = new SheetDiscount(DiscountRuleType::Price, '10.00', null, 'ПРЕДОСТАВЛЯЕТСЯ СКИДКА -10%');

    expect((new EffectivePriceCalculator)->calculate('43.10', null, [$discount]))->toBe('38.79');
});

test('a price from the discounted price column is used as is', function () {
    $discount = new SheetDiscount(DiscountRuleType::Price, '5.00', null, '-5% Скидка!');

    expect((new EffectivePriceCalculator)->calculate('320.00', '304.00', [$discount]))->toBe('304.00');
});

test('goods and conditional discounts do not change the price', function (SheetDiscount $discount) {
    expect((new EffectivePriceCalculator)->calculate('96.00', null, [$discount]))->toBe('96.00');
})->with([
    'goods discount' => fn () => new SheetDiscount(DiscountRuleType::Goods, '5.00', null, 'Товарная скидка 5%'),
    'discount from an order sum' => fn () => new SheetDiscount(DiscountRuleType::Price, '5.00', '10000.00', 'От суммы выше 10тыс манат финансовая скидка 5%', true),
    'discount for cash payment' => fn () => new SheetDiscount(DiscountRuleType::Price, '5.00', null, '"5%" скидка при оплате наличными', true),
]);
