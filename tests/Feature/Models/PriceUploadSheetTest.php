<?php

use App\Enums\PriceUploadSheetStatus;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use Illuminate\Database\UniqueConstraintViolationException;

test('an upload cannot have two sheets at the same position', function () {
    $upload = PriceUpload::factory()->create();
    PriceUploadSheet::factory()->for($upload)->create(['sheet_index' => 0]);

    PriceUploadSheet::factory()->for($upload)->create(['sheet_index' => 0]);
})->throws(UniqueConstraintViolationException::class);

test('different uploads may each have a sheet at the same position', function () {
    PriceUploadSheet::factory()->count(2)->create(['sheet_index' => 0]);

    expect(PriceUploadSheet::query()->where('sheet_index', 0)->count())->toBe(2);
});

test('status and the raw preview rows are read back as an enum and an array', function () {
    $preview = [
        ['row' => 1, 'cells' => ['ПРАЙС ЛИСТ']],
        ['row' => 4, 'cells' => ['№', 'Наименование', 'Цена']],
        ['row' => 6, 'cells' => [1, 'Цитрамон №6', '2,50 man.']],
    ];

    $sheet = PriceUploadSheet::factory()->needsReview()->create(['preview_json' => $preview])->fresh();

    expect($sheet->status)->toBe(PriceUploadSheetStatus::NeedsReview)
        ->and($sheet->preview_json)->toBe($preview);
});
