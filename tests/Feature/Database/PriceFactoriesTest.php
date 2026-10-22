<?php

use App\Models\DiscountRule;
use App\Models\PharmacySheetMapping;
use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use Illuminate\Database\Eloquent\Model;

test('the factory creates a record with its related models', function (string $model, array $relations) {
    /** @var class-string<Model> $model */
    $record = $model::factory()->create();

    $loaded = $model::query()->with($relations)->findOrFail($record->getKey());

    foreach ($relations as $relation) {
        expect($loaded->getRelation($relation))->not->toBeNull();
    }
})->with([
    'upload' => [PriceUpload::class, ['user']],
    'upload sheet' => [PriceUploadSheet::class, ['priceUpload', 'pharmacy']],
    'sheet mapping' => [PharmacySheetMapping::class, ['pharmacy']],
    'price item' => [PriceItem::class, ['priceUpload', 'pharmacy']],
    'discount rule' => [DiscountRule::class, ['priceUpload', 'pharmacy']],
]);

test('an upload reaches its sheets, items and discount rules through relations', function () {
    $upload = PriceUpload::factory()
        ->has(PriceUploadSheet::factory()->count(2), 'sheets')
        ->has(PriceItem::factory()->count(3), 'items')
        ->has(DiscountRule::factory()->price(), 'discountRules')
        ->create();

    $loaded = PriceUpload::query()->withCount(['sheets', 'items', 'discountRules'])->findOrFail($upload->id);

    expect([$loaded->sheets_count, $loaded->items_count, $loaded->discount_rules_count])->toBe([2, 3, 1]);
});
