<?php

use App\Services\Prices\Parsing\LatinTransliterator;

test('spells a drug name typed in Latin letters in Cyrillic', function (string $latin, string $cyrillic) {
    expect((new LatinTransliterator)->toCyrillic($latin))->toBe($cyrillic);
})->with([
    'plain letters' => ['Analgin', 'аналгин'],
    'c before i sounds like ц' => ['citramon', 'цитрамон'],
    'c before o sounds like к' => ['coldrex', 'колдрекс'],
    'letter pairs' => ['shchit chek zhel', 'щит чек жел'],
    'turkmen letters' => ['Şeker çaý', 'шекер чай'],
    'numbers and signs are kept' => ['Paracetamol 500mg №10', 'парацетамол 500мг №10'],
]);

test('a name without Latin letters needs no other spelling', function () {
    expect((new LatinTransliterator)->toCyrillic('Аналгин 500мг №10'))->toBeNull();
});
