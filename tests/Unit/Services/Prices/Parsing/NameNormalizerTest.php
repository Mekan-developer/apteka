<?php

use App\Services\Prices\Parsing\NameNormalizer;

test('different spellings of one drug meet in one base name', function (string $name) {
    expect((new NameNormalizer)->normalize($name)->baseName)->toBe('цитрамон боримед');
})->with([
    'Цитрамон-Боримед таб №6',
    'Цитрамон - Боримед таблетки 200мг №6 Беларусь',
]);

test('reads the pack size from the name', function (string $name, int $packCount) {
    expect((new NameNormalizer)->normalize($name)->packCount)->toBe($packCount);
})->with([
    'number sign' => ['Цитрамон П №10', 10],
    'number sign with a space' => ['ЦИТРАМОН-П (бумажном/упаковке) таблетки № 6', 6],
    'Latin N' => ['Цитрамон N6', 6],
    'hash' => ['Цитрамон #6', 6],
]);

test('a name without a pack size has none', function () {
    expect((new NameNormalizer)->normalize('Алфагин сироп 120мл.')->packCount)->toBeNull();
});

test('takes the active ingredient from parentheses', function (string $name, string $baseName, string $ingredient) {
    $normalized = (new NameNormalizer)->normalize($name);

    expect($normalized->baseName)->toBe($baseName)
        ->and($normalized->activeIngredient)->toBe($ingredient);
})->with([
    'brand in Latin' => ['ACTION PLUS (ЦИТРАМОН) №4', 'action plus', 'цитрамон'],
    'ingredient with a dose' => ['Регамол (парацетамол 500мг)', 'регамол', 'парацетамол'],
    'hyphenated brand' => ['Ал-Панадол (Парацетамол)', 'ал панадол', 'парацетамол'],
]);

test('collects every dose of the name', function () {
    expect((new NameNormalizer)->normalize('Альбувен (альбумин) 20% 50мл.')->dose)->toBe('20% 50мл');
});

test('typos in vowels give the same skeleton', function () {
    $normalizer = new NameNormalizer;

    expect($normalizer->normalize('Аналгин')->skeleton)->toBe('нлгн')
        ->and($normalizer->normalize('Анолгин 10мг')->skeleton)->toBe('нлгн');
});

test('Latin lookalikes inside a Cyrillic word become Cyrillic', function () {
    // "Нaфтизин" is typed with a Latin "a".
    expect((new NameNormalizer)->normalize('Нaфтизин капли 0,1% 15мл')->baseName)->toBe('нафтизин');
});

test('drops the dosage form and its abbreviations from the base name', function () {
    expect((new NameNormalizer)->normalize('АЦЦ 200 пор. д/приг р-ра д/пр внутрь 200мг/3г №20')->baseName)->toBe('ацц');
});

test('a bilingual name keeps only its Russian part', function () {
    $normalized = (new NameNormalizer)->normalize('Ампициллин капсулы 500 мг  капсулы №100 / AMPICILLIN CAPSULES 500 mg süýri däneler №100');

    expect($normalized->normalized)->toBe('ампициллин капсулы 500 мг капсулы №100')
        ->and($normalized->baseName)->toBe('ампициллин');
});
