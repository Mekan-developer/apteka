<?php

use App\Enums\PriceUploadSheetStatus;
use App\Enums\PriceUploadStatus;
use App\Jobs\ReparsePriceSheetJob;
use App\Models\DiscountRule;
use App\Models\Pharmacy;
use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A sheet of an imported upload whose preview shows rows 1–3.
 */
function reviewedSheet(PriceUploadStatus $uploadStatus = PriceUploadStatus::NeedsReview): PriceUploadSheet
{
    return PriceUploadSheet::factory()
        ->for(PriceUpload::factory()->state(['status' => $uploadStatus]))
        ->needsReview()
        ->create([
            'sheet_name' => 'Custom',
            'error' => 'columns_not_detected',
            'preview_json' => [
                ['row' => 1, 'cells' => ['Прайс-лист']],
                ['row' => 2, 'cells' => ['Товар', 'Стоимость']],
                ['row' => 3, 'cells' => ['Бетасерк 16мг таб №30', 96]],
            ],
        ]);
}

test('the sheet page shows the raw rows, the chosen columns and what was parsed for its pharmacy', function () {
    $sheet = PriceUploadSheet::factory()->create(['sheet_name' => 'Kamat', 'header_row' => 4, 'name_col' => 1, 'price_col' => 4]);
    PriceItem::factory()->for($sheet->priceUpload)->for($sheet->pharmacy)->create(['raw_name' => 'Алфагин сироп 120мл.', 'source_row' => 6]);
    PriceItem::factory()->for($sheet->priceUpload)->for(Pharmacy::factory())->create(['raw_name' => 'Другая аптека']);
    DiscountRule::factory()->for($sheet->priceUpload)->for($sheet->pharmacy)->create(['raw_text' => 'Товарная скидка 5%']);

    $this->actingAs(User::factory()->create())
        ->get(route('price-uploads.sheets.show', [$sheet->priceUpload, $sheet]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('price-uploads/sheets/Show')
            ->where('sheet.sheet_name', 'Kamat')
            ->where('sheet.header_row', 4)
            ->where('sheet.name_col', 1)
            ->where('sheet.price_col', 4)
            ->where('sheet.preview.2.cells.1', 'Цитрамон таб. №6')
            ->has('items', 1)
            ->where('items.0.raw_name', 'Алфагин сироп 120мл.')
            ->where('discounts.0.raw_text', 'Товарная скидка 5%')
        );
});

test('a sheet is only found under its own upload', function () {
    $sheet = PriceUploadSheet::factory()->create();
    $otherUpload = PriceUpload::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('price-uploads.sheets.show', [$otherUpload, $sheet]))
        ->assertNotFound();
});

test('choosing columns queues parsing the sheet again', function () {
    Queue::fake();
    $sheet = reviewedSheet();

    $this->actingAs(User::factory()->create())
        ->put(route('price-uploads.sheets.update', [$sheet->priceUpload, $sheet]), [
            'header_row' => 2,
            'name_col' => 0,
            'price_col' => 1,
            'price_discounted_col' => null,
        ])
        ->assertRedirect(route('price-uploads.sheets.show', [$sheet->priceUpload, $sheet]))
        ->assertSessionHas('notify', [
            'type' => 'info',
            'message' => 'Лист «Custom» разбирается заново с выбранными колонками. Результат появится через несколько секунд.',
        ]);

    expect($sheet->refresh()->status)->toBe(PriceUploadSheetStatus::Processing);

    Queue::assertPushed(ReparsePriceSheetJob::class, fn (ReparsePriceSheetJob $job): bool => $job->sheet->is($sheet)
        && [$job->headerRow, $job->nameCol, $job->priceCol, $job->priceDiscountedCol] === [2, 0, 1, null]);
});

test('the name and the price cannot be read from the same column', function () {
    Queue::fake();
    $sheet = reviewedSheet();

    $this->actingAs(User::factory()->create())
        ->put(route('price-uploads.sheets.update', [$sheet->priceUpload, $sheet]), ['header_row' => 2, 'name_col' => 1, 'price_col' => 1])
        ->assertSessionHasErrors(['price_col' => 'Поля «Колонка цены» и «Колонка названия» должны различаться.']);

    Queue::assertNothingPushed();
});

test('the header row must be one of the rows the admin saw', function () {
    Queue::fake();
    $sheet = reviewedSheet();

    $this->actingAs(User::factory()->create())
        ->put(route('price-uploads.sheets.update', [$sheet->priceUpload, $sheet]), ['header_row' => 40, 'name_col' => 0, 'price_col' => 1])
        ->assertSessionHasErrors(['header_row' => 'Выбрано недопустимое значение поля «Строка заголовка».']);

    Queue::assertNothingPushed();
});

test('a sheet is not parsed again while the whole file is still being imported', function () {
    Queue::fake();
    $sheet = reviewedSheet(PriceUploadStatus::Processing);

    $this->actingAs(User::factory()->create())
        ->put(route('price-uploads.sheets.update', [$sheet->priceUpload, $sheet]), ['header_row' => 2, 'name_col' => 0, 'price_col' => 1])
        ->assertSessionHas('notify', [
            'type' => 'warning',
            'message' => 'Сейчас лист нельзя разобрать заново: файл или этот лист ещё обрабатываются. Попробуйте через минуту.',
        ]);

    expect($sheet->refresh()->status)->toBe(PriceUploadSheetStatus::NeedsReview);
    Queue::assertNothingPushed();
});
