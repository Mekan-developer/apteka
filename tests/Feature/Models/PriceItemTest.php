<?php

use App\Models\PriceItem;
use Illuminate\Support\Facades\Schema;

test('prices are read back with exactly two decimals', function () {
    $item = PriceItem::factory()->create(['price' => 2.5, 'effective_price' => 2.375])->fresh();

    expect($item->price)->toBe('2.50')
        ->and($item->effective_price)->toBe('2.38');
});

test('a misspelled drug name finds the product by trigram similarity', function () {
    PriceItem::factory()->create(['base_name' => 'аналгин']);
    PriceItem::factory()->create(['base_name' => 'цитрамон']);

    // 0.4 is the starting search threshold from docs/price-search.md; this pair scores 0.45.
    $found = PriceItem::query()->whereRaw('word_similarity(?, base_name) >= 0.4', ['анолгин'])->pluck('base_name');

    expect($found->all())->toBe(['аналгин']);
});

test('names searched by similarity have trigram gin indexes', function () {
    $trigramColumns = collect(Schema::getIndexes('price_items'))
        ->filter(fn (array $index): bool => $index['type'] === 'gin')
        ->flatMap(fn (array $index): array => $index['columns'])
        ->sort()
        ->values()
        ->all();

    expect($trigramColumns)->toBe(['active_ingredient', 'base_name', 'name_normalized']);
});
