<?php

use App\Enums\DosageForm;
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

test('spellings with and without a soft sign meet', function () {
    $normalizer = new NameNormalizer;

    expect($normalizer->normalize('Анальгин таб №10')->baseName)->toBe('аналгин')
        ->and($normalizer->normalize('Аналгин таб №10')->baseName)->toBe('аналгин');
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

test('reads the dosage form from the name', function (string $name, ?DosageForm $form) {
    expect((new NameNormalizer)->normalize($name)->dosageForm)->toBe($form);
})->with([
    'tablets' => ['Азитромицин Сандоз 500мг таблетки №3', DosageForm::Tablets],
    'tablets abbreviated' => ['Азитромицин-Акра табл п/п/о 500мг №3', DosageForm::Tablets],
    'capsules' => ['Верона капс. №20', DosageForm::Capsules],
    'syrup' => ['Алфагин сироп 120мл.', DosageForm::Syrup],
    'powder for a suspension is a suspension' => ['Азитромицин Лек 200мг/5мл пор.для приг.сусп. для приема внутрь во фл. 20мл', DosageForm::Suspension],
    'solution for injections is an injection' => ['Витамин В1 Тиамин хлорид р-р д/инъ. 5% - 1мл №10', DosageForm::Injection],
    'ampoules' => ['Но-шпа ампула №5', DosageForm::Injection],
    'ampicillin is not an ampoule' => ['Ампициллин 250мг №20', null],
    'drops' => ['Нафтизин капли 0,1% 15мл', DosageForm::Drops],
    'ointment' => ['Левомеколь мазь 40г', DosageForm::Ointment],
    'suppositories' => ['Парацетамол свечи 250мг №10', DosageForm::Suppositories],
    'powder' => ['АЦЦ 200 пор. д/приг р-ра д/пр внутрь 200мг/3г №20', DosageForm::Powder],
    'oral solution' => ['Регидрон раствор 200мл', DosageForm::Solution],
    'no form in the name' => ['Азитромицин 500 мг. №3', null],
]);

test('writes equal doses the same way', function (string $name, string $dose) {
    expect((new NameNormalizer)->normalize($name)->dose)->toBe($dose);
})->with([
    'trailing zeros' => ['Анальгин р-р 50% 2,0 мл №10', '50% 2мл'],
    'volume before the strength' => ['Анальгин амп 2 мл 500 мг №10', '500мг 2мл'],
    'concentration and volume keep their order' => ['Азитромицин Лек 200мг/5мл суспензия 20мл', '200мг 5мл 20мл'],
]);
