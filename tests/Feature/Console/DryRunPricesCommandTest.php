<?php

test('reports every sheet with its status, products and the reason it needs review', function () {
    $path = xlsxFile([
        'Ashgabat' => [
            ['От суммы выше 10тыс манат финансовая скидка 5% на все'],
            ['Наименование', 'Цена', 'Производитель'],
            ['Бетасерк 16мг таб №30', '96,00', 'Абботт'],
            ['Бруфен Рапид 400мг капс №10', '28,00', 'Абботт'],
        ],
        'Notes' => [['Прайс-лист'], ['Тел: +99362694637']],
    ]);

    // Each table row satisfies one expectation, so there is one per row.
    $this->artisan('prices:dry-run', ['path' => $path])
        ->expectsOutputToContain(__('prices.discounts.from_sum', ['discount' => __('prices.discounts.price', ['percent' => 5]), 'sum' => '10 000']))
        ->expectsOutputToContain(__('prices.review_reasons.columns_not_detected'))
        ->assertSuccessful();
});

test('lists the parsed products of the requested sheet', function () {
    $path = xlsxFile([
        'Ashgabat' => [
            ['Наименование', 'Цена'],
            ['Бетасерк 16мг таб №30', '96,00'],
        ],
    ]);

    $this->artisan('prices:dry-run', ['path' => $path, '--sheet' => 'Ashgabat'])
        ->expectsOutputToContain('| бетасерк')
        ->assertSuccessful();
});

test('says so when the requested sheet is not in the file', function () {
    $path = xlsxFile(['Ashgabat' => [['Наименование', 'Цена'], ['Бетасерк 16мг таб №30', '96,00']]]);

    $this->artisan('prices:dry-run', ['path' => $path, '--sheet' => 'Kamat'])
        ->expectsOutputToContain(__('prices.dry_run.sheet_not_found', ['name' => 'Kamat']))
        ->assertSuccessful();
});

test('fails when the file does not exist', function () {
    $path = sys_get_temp_dir().'/missing-prices.xlsx';

    $this->artisan('prices:dry-run', ['path' => $path])
        ->expectsOutputToContain(__('prices.file_not_found', ['path' => $path]))
        ->assertFailed();
});
