<?php

use App\Services\Prices\Parsing\MergedCells;

test('drops the cells a merged range covers so the row reads as it looks in excel', function () {
    $rows = [
        2 => ['Бромгексин 8мг таб №25', 'уп.', '', '18,50'],
        3 => ['Мезим форте таб №20', 'уп.', '', '25,00'],
        4 => ['Анальгин таб 500мг №10', 'уп.', '4,00'],
    ];

    expect(MergedCells::collapse($rows, ['B2:C3', 'A9:B9']))->toBe([
        2 => ['Бромгексин 8мг таб №25', 'уп.', '18,50'],
        3 => ['Мезим форте таб №20', 'уп.', '25,00'],
        4 => ['Анальгин таб 500мг №10', 'уп.', '4,00'],
    ]);
});
