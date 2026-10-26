<?php

use App\Models\PriceUpload;

test('names starting with the typed letters are suggested, those sold by more pharmacies first', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Азипос 250мг №10', 9);
    offer($upload, 'Kamat', 'Азитромицин таб 500мг №3', 12);
    offer($upload, 'ASDS', 'Азитромицин 500 мг таб. №3', 11);
    offer($upload, 'ASDS', 'Цитрамон таб №6', 1.5);

    $this->getJson(route('api.v1.drug-suggestions', ['q' => 'Аз']))
        ->assertOk()
        ->assertExactJson([
            'data' => [
                ['name' => 'Азитромицин', 'pharmacies_count' => 2],
                ['name' => 'Азипос', 'pharmacies_count' => 1],
            ],
            'message' => 'Найдено подсказок: 2.',
        ]);
});

test('the active ingredient is suggested too', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Ал-Панадол (Парацетамол) таб 500мг №10', 3);

    $this->getJson(route('api.v1.drug-suggestions', ['q' => 'пара']))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Парацетамол');
});

test('letters typed in Latin suggest the Cyrillic names', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Азитромицин таб 500мг №3', 12);

    $this->getJson(route('api.v1.drug-suggestions', ['q' => 'azit']))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Азитромицин');
});

test('a typo in the first letters still suggests the name', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Азитромицин таб 500мг №3', 12);

    $this->getJson(route('api.v1.drug-suggestions', ['q' => 'озитромицин']))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Азитромицин');
});

test('only names from the active upload are suggested', function () {
    offer(PriceUpload::factory()->archived()->create(), 'Old', 'Азипос 250мг №10', 9);
    offer(PriceUpload::factory()->active()->create(), 'Kamat', 'Азитромицин таб 500мг №3', 12);

    $this->getJson(route('api.v1.drug-suggestions', ['q' => 'аз']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Азитромицин');
});

test('nothing similar answers with an empty list and a message', function () {
    offer(PriceUpload::factory()->active()->create(), 'Kamat', 'Азитромицин таб 500мг №3', 12);

    $this->getJson(route('api.v1.drug-suggestions', ['q' => 'хю']))
        ->assertOk()
        ->assertExactJson(['data' => [], 'message' => 'Похожих названий в прайсах нет.']);
});

test('fewer than two typed letters are refused with a localized message', function (?string $query, string $message) {
    $this->getJson(route('api.v1.drug-suggestions', ['q' => $query]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q' => $message]);
})->with([
    'one letter' => ['а', 'Введите хотя бы 2 буквы названия препарата.'],
    'nothing' => [null, 'Поле «Название препарата» обязательно для заполнения.'],
]);

test('one visitor may ask for at most 120 suggestions a minute', function () {
    for ($request = 0; $request < 120; $request++) {
        $this->getJson(route('api.v1.drug-suggestions', ['q' => 'аз']))->assertOk();
    }

    $this->getJson(route('api.v1.drug-suggestions', ['q' => 'аз']))->assertTooManyRequests();
});
