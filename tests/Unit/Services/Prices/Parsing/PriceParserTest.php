<?php

use App\Services\Prices\Parsing\PriceParser;

test('reads the price written in a cell', function (mixed $value, string $expected) {
    expect((new PriceParser)->parse($value))->toBe($expected);
})->with([
    'number' => [43.1, '43.10'],
    'whole number' => [152, '152.00'],
    'comma decimal' => ['96,00', '96.00'],
    'manat with dot' => ['2,50 man.', '2.50'],
    'short manat' => ['55,00m.', '55.00'],
    'manat without dot' => ['5.50 man', '5.50'],
    'teňňe' => ['0,48 teňňe.', '0.48'],
    'thousands separated by a space' => ['1 250,00', '1250.00'],
    'float noise' => ['1.1000000000000001', '1.10'],
]);

test('gives no price for a cell that holds something else', function (mixed $value) {
    expect((new PriceParser)->parse($value))->toBeNull();
})->with([
    'expiry date as text' => ['15.12.2027'],
    'expiry date' => [new DateTimeImmutable('2028-02-01')],
    'box quantity' => ['50/400'],
    'percent' => ['5%'],
    'out of stock' => ['ожидаем'],
    'empty' => [''],
    'zero' => [0],
    'negative' => [-5],
    'boolean' => [true],
]);
