<?php

use App\Services\Prices\Parsing\ContactExtractor;

test('reads the phones a pharmacy writes above its table as numbers to dial', function (string|int $cell, array $phones) {
    expect((new ContactExtractor)->extract([1 => [$cell]])->phones)->toBe($phones);
})->with([
    'with the country code' => ['заявки по тел: +993 71 24 14 29', ['+99371241429']],
    'an Ashgabat landline' => ['Наименование 20-97-85', ['+99312209785']],
    'with the trunk prefix' => ['ЗАКАЗЫ ПО ТЕЛЕФОНУ: 862-65-00-25', ['+99362650025']],
    'a mobile without the country code' => ['Моб: +993 65 974444 Бегли', ['+99365974444']],
    'stored by Excel as a number' => [99364320482, ['+99364320482']],
    'several on one line' => ['+99364705550 +99363300359; (+993)61-00-09-96', ['+99364705550', '+99363300359', '+99361000996']],
]);

test('dates, order sums and postcodes are not phones', function (string $cell) {
    expect((new ContactExtractor)->extract([1 => [$cell]])->phones)->toBe([]);
})->with([
    'a date' => ['19 10 2026'],
    'a date with dots' => ['Новое поступление 18.09.2026г.'],
    'an order sum' => ['ПРИ ЗАКАЗЕ НА СУММУ ОТ 10000 МАНАТ- 5% ТОВАРНАЯ СКИДКА'],
    'a big order sum' => ['от 100 000 ман -3%'],
    'a postcode' => ['744000, Aşgabat şäheri'],
]);

test('keeps at most three phones and each only once', function () {
    $contacts = (new ContactExtractor)->extract([
        1 => ['Тел: 22-98-30; 22-98-35', 'тел. 22-98-30'],
        2 => ['+99365109606', '45 24 69'],
    ]);

    expect($contacts->phones)->toBe(['+99312229830', '+99312229835', '+99365109606']);
});

test('reads the address and leaves out the phones written after it', function (string $cell, string $address) {
    expect((new ContactExtractor)->extract([1 => [$cell]])->address)->toBe($address);
})->with([
    'after a label' => ['НАШ адрес : ул. Огузхан, дом 106 "В"', 'ул. Огузхан, дом 106 "В"'],
    'before the phones' => ['Адрес : г.Ашгабат, ул. 1946 Анкара (Юбилейная) дом 14 Телефон: 420-420', 'г.Ашгабат, ул. 1946 Анкара (Юбилейная) дом 14'],
    'after a company name' => ['ИП «Саг-Аман» г. Ашгабат ул. 1946 (Анкара) дом 119', 'г. Ашгабат ул. 1946 (Анкара) дом 119'],
    'in Turkmen' => ['744000, Aşgabat şäheri, A.Nyýazow şaýolunyň 190-njy jaýy', 'Aşgabat şäheri, A.Nyýazow şaýolunyň 190-njy jaýy'],
]);

test('an e-mail or the city alone is no address', function (string $cell) {
    expect((new ContactExtractor)->extract([1 => [$cell]])->address)->toBeNull();
})->with([
    'Эл.адрес: hkbayry@gmail.com',
    'e-mail адрес: gurtly ammar <saglykgurtly@mail.ru>',
    'Адрес: г. Ашхабад',
]);
