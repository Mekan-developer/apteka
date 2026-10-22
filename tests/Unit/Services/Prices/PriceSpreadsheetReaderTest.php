<?php

use App\Services\Prices\PriceSpreadsheetReader;
use App\Services\Prices\SheetPreview;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Exception\IOException;

/**
 * @return list<SheetPreview>
 */
function previewSheets(string $path, int $previewRowCount = 8, int $sheetsPerOpen = 20): array
{
    return iterator_to_array((new PriceSpreadsheetReader($sheetsPerOpen))->previewSheets($path, $previewRowCount), false);
}

test('previews every sheet with its non-empty row count and first rows as stored', function () {
    $path = xlsxFile([
        'Kamat' => [
            ['ПРАЙС ЛИСТ', null, null],
            [],
            ['№', 'Наименование', 'Цена', 'Срок годности'],
            ['   ', "\u{A0}\u{A0}"],
            [1, 'Цитрамон №6', '2,50 man.', null],
            [2, 'Аналгин', 1.1, 46784],
        ],
        'Пустой' => [],
    ]);

    [$kamat, $empty] = previewSheets($path);

    expect($kamat->index)->toBe(0)
        ->and($kamat->name)->toBe('Kamat')
        ->and($kamat->rowCount)->toBe(4)
        ->and($kamat->rows[0])->toBe(['ПРАЙС ЛИСТ'])
        ->and($kamat->rows[2])->toBe([1, 'Цитрамон №6', '2,50 man.'])
        ->and($kamat->rows[3])->toBe([2, 'Аналгин', 1.1, 46784])
        ->and($empty->index)->toBe(1)
        ->and($empty->name)->toBe('Пустой')
        ->and($empty->rowCount)->toBe(0)
        ->and($empty->rows)->toBe([]);
});

test('keeps date formatted cells as dates so an expiry date is never read as a number', function () {
    $expiry = Cell::fromValue(new DateTimeImmutable('2028-02-01'), (new Style)->setFormat('dd.mm.yyyy'));
    $path = xlsxFile(['Kamat' => [['Цитрамон №6', 1.5, $expiry]]]);

    $expiry = previewSheets($path)[0]->rows[0][2];

    expect($expiry)->toBeInstanceOf(DateTimeInterface::class)
        ->and($expiry->format('Y-m-d'))->toBe('2028-02-01');
});

test('limits the preview but counts every non-empty row', function () {
    $path = xlsxFile(['Kamat' => [['a'], ['b'], ['c'], ['d']]]);

    [$sheet] = previewSheets($path, previewRowCount: 2);

    expect($sheet->rowCount)->toBe(4)
        ->and($sheet->rows)->toBe([['a'], ['b']]);
});

test('reads every sheet in order when the file is reopened between sheets', function () {
    $path = xlsxFile(['Kamat' => [['a']], 'Akrap' => [['b']], 'ASDS' => [['c']]]);

    $sheets = previewSheets($path, sheetsPerOpen: 1);

    expect(array_map(fn (SheetPreview $sheet): string => $sheet->name, $sheets))->toBe(['Kamat', 'Akrap', 'ASDS'])
        ->and(array_map(fn (SheetPreview $sheet): array => $sheet->rows, $sheets))->toBe([[['a']], [['b']], [['c']]]);
});

test('gives the value excel computed for a formula cell instead of the formula', function () {
    $path = xlsxFile(['Akrap' => [[1, '=A1+1']]]);

    // The writer cannot compute formulas, so store the cached result the way Excel does.
    $zip = new ZipArchive;
    $zip->open($path);
    $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->addFromString('xl/worksheets/sheet1.xml', preg_replace('#<f>A1\+1</f>#', '<f>A1+1</f><v>2</v>', $sheetXml, 1));
    $zip->close();

    expect(previewSheets($path)[0]->rows[0])->toBe([1, 2]);
});

test('fails on a file that is not an xlsx file', function () {
    $path = tempnam(sys_get_temp_dir(), 'prices');
    file_put_contents($path, 'not a spreadsheet');

    try {
        previewSheets($path);
    } finally {
        unlink($path);
    }
})->throws(IOException::class);
