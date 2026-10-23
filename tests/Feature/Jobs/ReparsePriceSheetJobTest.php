<?php

use App\Enums\PriceUploadSheetStatus;
use App\Jobs\ImportPriceUploadJob;
use App\Jobs\ReparsePriceSheetJob;
use App\Models\PharmacySheetMapping;
use App\Models\PriceItem;
use App\Models\PriceUploadSheet;
use App\Services\Prices\Parsing\HeaderSignature;

/**
 * An imported upload whose «Custom» sheet has labels the parser does not know, so it needs review.
 */
function importedUploadWithCustomSheet(): PriceUploadSheet
{
    $upload = uploadWithSheets([
        'Kamat' => [['Наименование', 'Цена'], ['Цитрамон-Боримед таб №6', 1.5]],
        'Custom' => [
            ['Прайс-лист'],
            ['Товар', 'Стоимость', 'Бонус товаром'],
            ['Бетасерк 16мг таб №30', 96, 0.05],
            ['Бруфен Рапид 400мг капс №10', 28],
        ],
    ]);

    ImportPriceUploadJob::dispatchSync($upload);

    return PriceUploadSheet::query()->where('sheet_name', 'Custom')->sole();
}

test('parsing a sheet with chosen columns replaces its products and remembers the columns for the pharmacy', function () {
    $sheet = importedUploadWithCustomSheet();
    expect($sheet->status)->toBe(PriceUploadSheetStatus::NeedsReview);

    ReparsePriceSheetJob::dispatchSync($sheet, 2, 0, 1);

    $sheet->refresh();
    $mapping = PharmacySheetMapping::query()->sole();
    $upload = $sheet->priceUpload()->sole();

    expect($sheet->status)->toBe(PriceUploadSheetStatus::Parsed)
        ->and([$sheet->header_row, $sheet->name_col, $sheet->price_col, $sheet->rows_parsed])->toBe([2, 0, 1, 2])
        ->and(PriceItem::query()->whereBelongsTo($sheet->pharmacy()->sole())->pluck('raw_name')->all())
        ->toBe(['Бетасерк 16мг таб №30', 'Бруфен Рапид 400мг капс №10'])
        ->and($mapping->pharmacy_id)->toBe($sheet->pharmacy_id)
        ->and([$mapping->header_row, $mapping->name_col, $mapping->price_col])->toBe([2, 0, 1])
        ->and($mapping->header_signature)->toBe(HeaderSignature::of(['Товар', 'Стоимость', 'Бонус товаром']))
        ->and([$upload->sheets_ok, $upload->rows_total])->toBe([2, 3]);
});

test('the goods discount column is still read when an admin chose the columns', function () {
    $sheet = importedUploadWithCustomSheet();

    ReparsePriceSheetJob::dispatchSync($sheet, 2, 0, 1);

    expect(PriceItem::query()->where('raw_name', 'Бетасерк 16мг таб №30')->sole()->goods_discount_percent)->toBe('5.00');
});

test('parsing again with the same columns gives the same result', function () {
    $sheet = importedUploadWithCustomSheet();

    ReparsePriceSheetJob::dispatchSync($sheet, 2, 0, 1);
    ReparsePriceSheetJob::dispatchSync($sheet, 2, 0, 1);

    expect(PriceItem::query()->count())->toBe(3)
        ->and(PharmacySheetMapping::query()->count())->toBe(1);
});

test('columns that still give no prices are not remembered', function () {
    $sheet = importedUploadWithCustomSheet();

    ReparsePriceSheetJob::dispatchSync($sheet, 2, 1, 0);

    expect($sheet->refresh()->status)->toBe(PriceUploadSheetStatus::NeedsReview)
        ->and(PharmacySheetMapping::query()->count())->toBe(0);
});

test('a sheet whose parsing failed on every attempt is marked as failed', function () {
    $sheet = PriceUploadSheet::factory()->create(['status' => PriceUploadSheetStatus::Processing]);

    (new ReparsePriceSheetJob($sheet, 2, 0, 1))->failed(new RuntimeException('Could not open the file.'));

    expect($sheet->refresh()->status)->toBe(PriceUploadSheetStatus::Failed)
        ->and($sheet->error)->toBe('Could not open the file.');
});
