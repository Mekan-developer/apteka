<?php

test('prints every sheet with its row count, first rows and a summary', function () {
    $path = xlsxFile([
        'Kamat' => [['№', 'Наименование', 'Цена'], [1, 'Цитрамон №6', '2,50 man.']],
        'Пустой' => [],
    ]);

    $this->artisan('prices:inspect', ['path' => $path])
        ->expectsOutputToContain(__('prices.inspect.sheet', ['index' => 1, 'name' => 'Kamat', 'rows' => 2]))
        ->expectsOutputToContain('Цитрамон №6')
        ->expectsOutputToContain(__('prices.inspect.sheet', ['index' => 2, 'name' => 'Пустой', 'rows' => 0]))
        ->expectsOutputToContain(__('prices.inspect.empty_sheet'))
        ->assertSuccessful();
});

test('fails when the file does not exist', function () {
    $path = sys_get_temp_dir().'/missing-prices.xlsx';

    $this->artisan('prices:inspect', ['path' => $path])
        ->expectsOutputToContain(__('prices.file_not_found', ['path' => $path]))
        ->assertFailed();
});

test('fails when the file is not an xlsx file', function () {
    $path = tempnam(sys_get_temp_dir(), 'prices');
    file_put_contents($path, 'not a spreadsheet');

    $this->artisan('prices:inspect', ['path' => $path])->assertFailed();

    unlink($path);
});
