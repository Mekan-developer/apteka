<?php

use App\Enums\DiscountRuleType;
use App\Enums\PriceUploadSheetStatus;
use App\Enums\PriceUploadStatus;
use App\Jobs\ImportPriceUploadJob;
use App\Models\DiscountRule;
use App\Models\Pharmacy;
use App\Models\PharmacySheetMapping;
use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use App\Services\Prices\Parsing\HeaderSignature;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Exception\IOException;

/**
 * @return array<string, list<list<mixed>>>
 */
function weeklySheets(): array
{
    return [
        'Ashgabat' => [
            ['От суммы выше 10тыс манат финансовая скидка 5% на все'],
            ['Наименование', 'Цена', 'Производитель'],
            ['Бетасерк 16мг таб №30', '96,00', 'Абботт'],
            ['Бруфен Рапид 400мг капс №10', '28,00', 'Абботт'],
        ],
        'Kamat' => [
            ['№', 'Наименование', 'Цена', 'Товарная нагрузка'],
            [1, 'Цитрамон-Боримед таб №6', 1.5, 0.03],
            [2, 'Анальгин таб 500мг №10', 3.1],
        ],
        'Notes' => [['Прайс-лист'], ['Тел: +99362694637']],
    ];
}

test('importing a price file saves every pharmacy with its products and discounts', function () {
    $upload = uploadWithSheets(weeklySheets());

    ImportPriceUploadJob::dispatchSync($upload);

    $upload->refresh();
    $citramon = PriceItem::query()->where('raw_name', 'Цитрамон-Боримед таб №6')->sole();
    $discount = DiscountRule::query()->sole();

    expect($upload->status)->toBe(PriceUploadStatus::NeedsReview)
        ->and([$upload->sheets_total, $upload->sheets_ok, $upload->rows_total])->toBe([3, 2, 4])
        ->and(Pharmacy::query()->orderBy('name')->pluck('name')->all())->toBe(['Ashgabat', 'Kamat', 'Notes'])
        ->and($citramon->pharmacy->name)->toBe('Kamat')
        ->and($citramon->base_name)->toBe('цитрамон боримед')
        ->and($citramon->pack_count)->toBe(6)
        ->and($citramon->price)->toBe('1.50')
        ->and($citramon->effective_price)->toBe('1.50')
        ->and($citramon->goods_discount_percent)->toBe('3.00')
        ->and($citramon->source_row)->toBe(2)
        ->and($discount->pharmacy->name)->toBe('Ashgabat')
        ->and($discount->type)->toBe(DiscountRuleType::Price)
        ->and($discount->min_sum)->toBe('10000.00')
        ->and($discount->is_conditional)->toBeTrue();
});

test('every sheet keeps its status, detected columns and first raw rows for the review screen', function () {
    $upload = uploadWithSheets(weeklySheets());

    ImportPriceUploadJob::dispatchSync($upload);

    $sheets = PriceUploadSheet::query()->orderBy('sheet_index')->get();

    expect($sheets->pluck('status')->all())->toBe([
        PriceUploadSheetStatus::Parsed,
        PriceUploadSheetStatus::Parsed,
        PriceUploadSheetStatus::NeedsReview,
    ])
        ->and([$sheets[1]->header_row, $sheets[1]->name_col, $sheets[1]->price_col, $sheets[1]->rows_parsed])->toBe([1, 1, 2, 2])
        ->and($sheets[2]->error)->toBe('columns_not_detected')
        ->and($sheets[2]->preview_json)->toBe([['row' => 1, 'cells' => ['Прайс-лист']], ['row' => 2, 'cells' => ['Тел: +99362694637']]]);
});

test('importing the same upload again replaces the earlier result', function () {
    $upload = uploadWithSheets(weeklySheets());

    ImportPriceUploadJob::dispatchSync($upload);
    ImportPriceUploadJob::dispatchSync($upload);

    expect(PriceItem::query()->count())->toBe(4)
        ->and(PriceUploadSheet::query()->count())->toBe(3)
        ->and(DiscountRule::query()->count())->toBe(1)
        ->and(Pharmacy::query()->count())->toBe(3)
        ->and($upload->refresh()->rows_total)->toBe(4);
});

test('the columns an admin chose for a pharmacy are used while its header is unchanged', function () {
    $pharmacy = Pharmacy::factory()->create(['name' => 'Custom']);
    PharmacySheetMapping::factory()->for($pharmacy)->create([
        'header_row' => 1,
        'name_col' => 0,
        'price_col' => 1,
        'header_signature' => HeaderSignature::of(['Товар', 'Стоимость']),
    ]);
    $upload = uploadWithSheets(['Custom' => [['Товар', 'Стоимость'], ['Бетасерк 16мг таб №30', 96]]]);

    ImportPriceUploadJob::dispatchSync($upload);

    expect(PriceUploadSheet::query()->sole()->status)->toBe(PriceUploadSheetStatus::Parsed)
        ->and(PriceItem::query()->sole()->raw_name)->toBe('Бетасерк 16мг таб №30');
});

test('a remembered mapping is not applied once the sheet header has changed', function () {
    $pharmacy = Pharmacy::factory()->create(['name' => 'Custom']);
    PharmacySheetMapping::factory()->for($pharmacy)->create([
        'header_row' => 1,
        'name_col' => 0,
        'price_col' => 1,
        'header_signature' => HeaderSignature::of(['Товар', 'Стоимость']),
    ]);
    $upload = uploadWithSheets(['Custom' => [['Товар', 'Стоимость, ман'], ['Бетасерк 16мг таб №30', 96]]]);

    ImportPriceUploadJob::dispatchSync($upload);

    expect(PriceUploadSheet::query()->sole()->status)->toBe(PriceUploadSheetStatus::NeedsReview)
        ->and(PriceItem::query()->count())->toBe(0);
});

test('a stored file that is not a spreadsheet makes the import fail', function () {
    Storage::fake('local');
    Storage::disk('local')->put('price-uploads/broken.xlsx', 'not a spreadsheet');
    $upload = PriceUpload::factory()->create(['stored_path' => 'price-uploads/broken.xlsx']);

    ImportPriceUploadJob::dispatchSync($upload);
})->throws(IOException::class);

test('an upload whose import failed on every attempt is marked as failed', function () {
    $upload = PriceUpload::factory()->create(['status' => PriceUploadStatus::Processing]);

    (new ImportPriceUploadJob($upload))->failed(new RuntimeException('Could not open the file.'));

    expect($upload->refresh()->status)->toBe(PriceUploadStatus::Failed);
});
