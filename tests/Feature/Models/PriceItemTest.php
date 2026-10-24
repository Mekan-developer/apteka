<?php

use App\Models\PriceItem;
use Illuminate\Support\Facades\Schema;

test('prices are read back with exactly two decimals', function () {
    $item = PriceItem::factory()->create(['price' => 2.5, 'effective_price' => 2.375])->fresh();

    expect($item->price)->toBe('2.50')
        ->and($item->effective_price)->toBe('2.38');
});

test('names searched by similarity have trigram gin indexes', function () {
    $trigramColumns = collect(Schema::getIndexes('price_items'))
        ->filter(fn (array $index): bool => $index['type'] === 'gin')
        ->flatMap(fn (array $index): array => $index['columns'])
        ->sort()
        ->values()
        ->all();

    expect($trigramColumns)->toBe(['active_ingredient', 'base_name', 'name_compact', 'name_normalized']);
});
