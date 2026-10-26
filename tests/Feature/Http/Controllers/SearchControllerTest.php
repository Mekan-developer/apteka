<?php

use App\Enums\PriceUploadStatus;
use App\Models\DiscountRule;
use App\Models\DrugSynonym;
use App\Models\Pharmacy;
use App\Models\PriceUpload;
use Inertia\Testing\AssertableInertia as Assert;

test('the search page opens without signing in', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('query', '')
            ->where('result.groups', [])
            ->where('pricesDate', null)
        );
});

test('a typo in the vowels still finds the drug', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'ASDS', 'Анальгин таб 500мг №10 Беларусь', 3.1);

    $this->get(route('home', ['q' => 'анолгин']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.groups.0.offers.0.raw_name', 'Анальгин таб 500мг №10 Беларусь')
            ->where('result.groups.0.offers.0.pharmacy.name', 'ASDS')
        );
});

test('a cheaper product that only looks like the query does not push the drug out', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Цитрамон таб №4', 1.5);
    offer($upload, 'Enejan', 'ДА ЗЕАГРА силденафил цитрат 50мг №4', 0.9);
    offer($upload, 'Makul', 'Анальгин таб №10', 1.2);
    offer($upload, 'Akrap', 'Баралгин таб №10', 0.8);

    $this->get(route('home', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('result.groups.0.offers', 1)
            ->where('result.groups.0.offers.0.raw_name', 'Цитрамон таб №4')
        );

    $this->get(route('home', ['q' => 'аналгин']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('result.groups.0.offers', 1)
            ->where('result.groups.0.offers.0.raw_name', 'Анальгин таб №10')
        );
});

test('a name typed without its hyphen is found', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Atajan Gos', 'Но-шпа ампула №5', 29.7);

    $this->get(route('home', ['q' => 'ношпа']))
        ->assertInertia(fn (Assert $page) => $page->where('result.groups.0.offers.0.raw_name', 'Но-шпа ампула №5'));
});

test('offers are grouped by pack size because only the same pack can be compared', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Цитрамон П №10', 2.6);
    offer($upload, 'Gumry', 'Цитрамон-Боримед таб №6', 1.5);

    $this->get(route('home', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('result.groups', 2)
            ->where('result.groups.0.pack_count', 6)
            ->where('result.groups.0.offers.0.price', '1.50')
            ->where('result.groups.1.pack_count', 10)
            ->where('result.groups.1.offers.0.price', '2.60')
        );
});

test('each pack size shows the three cheapest pharmacies, cheapest first, with one offer per pharmacy', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Цитрамон-Боримед таб №6', 1.5);
    offer($upload, 'Kamat', 'ЦИТРАМОН-П (бумажном/упаковке) таблетки № 6', 1.1);
    offer($upload, 'ASDS', 'Цитрамон - Боримед таблетки 200мг №6 Беларусь', 1.2);
    offer($upload, 'Gumry', 'Цитрамон таб №6', 2.0);
    offer($upload, 'Enejan', 'Цитрамон таб №6', 1.8);

    $this->get(route('home', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('result.groups', 1)
            ->has('result.groups.0.offers', 3)
            ->where('result.groups.0.offers.0.pharmacy.name', 'Kamat')
            ->where('result.groups.0.offers.0.price', '1.10')
            ->where('result.groups.0.offers.0.place', 1)
            ->where('result.groups.0.offers.1.pharmacy.name', 'ASDS')
            ->where('result.groups.0.offers.2.pharmacy.name', 'Enejan')
            ->where('result.groups.0.offers.2.place', 3)
        );
});

test('offers are ranked by the price after an unconditional discount and carry the sheet discount notes', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Rahmet', 'Бетасерк 16мг таб №30', 100, ['effective_price' => 90]);
    offer($upload, 'Ashgabat', 'Бетасерк 16мг таб №30', 96);
    $ashgabat = Pharmacy::query()->where('name', 'Ashgabat')->sole();
    DiscountRule::factory()->price()->for($upload)->for($ashgabat)->create([
        'min_sum' => 10000,
        'is_conditional' => true,
        'raw_text' => 'От суммы выше 10тыс манат финансовая скидка 5% на все',
    ]);

    $this->get(route('home', ['q' => 'бетасерк']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.groups.0.offers.0.pharmacy.name', 'Rahmet')
            ->where('result.groups.0.offers.0.effective_price', '90.00')
            ->where('result.groups.0.offers.0.price', '100.00')
            ->where('result.groups.0.offers.1.pharmacy.name', 'Ashgabat')
            ->where('result.groups.0.offers.1.sheet_discounts.0.min_sum', '10000.00')
            ->where('result.groups.0.offers.0.sheet_discounts', [])
        );
});

test('only the active upload is searched', function () {
    offer(PriceUpload::factory()->archived()->create(), 'Old', 'Цитрамон таб №6', 0.5);
    offer(PriceUpload::factory()->create(['status' => PriceUploadStatus::NeedsReview]), 'Next', 'Цитрамон таб №6', 0.7);
    offer(PriceUpload::factory()->active()->create(), 'Kamat', 'Цитрамон таб №6', 1.5);

    $this->get(route('home', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('result.groups.0.offers', 1)
            ->where('result.groups.0.offers.0.pharmacy.name', 'Kamat')
        );
});

test('a pack size in the query narrows the search to that pack', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Цитрамон П №10', 2.6);
    offer($upload, 'Gumry', 'Цитрамон таб №6', 1.5);

    $this->get(route('home', ['q' => 'цитрамон №10']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('result.groups', 1)
            ->where('result.groups.0.pack_count', 10)
        );
});

test('the active ingredient finds the drug under another brand name', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Abray Umyt', 'ACTION PLUS (ЦИТРАМОН) №4', 1.1);

    $this->get(route('home', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page->where('result.groups.0.offers.0.raw_name', 'ACTION PLUS (ЦИТРАМОН) №4'));
});

test('a drug name typed in Latin letters finds the drug written in Cyrillic', function (string $query) {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'ASDS', 'Анальгин таб 500мг №10 Беларусь', 3.1);

    $this->get(route('home', ['q' => $query]))
        ->assertInertia(fn (Assert $page) => $page->where('result.groups.0.offers.0.raw_name', 'Анальгин таб 500мг №10 Беларусь'));
})->with(['analgin', 'Analgin tab №10', 'anolgin']);

test('a brand stored in Latin letters is still found by its Latin name', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Abray Umyt', 'ACTION PLUS (ЦИТРАМОН) №4', 1.1);

    $this->get(route('home', ['q' => 'action plus']))
        ->assertInertia(fn (Assert $page) => $page->where('result.groups.0.offers.0.raw_name', 'ACTION PLUS (ЦИТРАМОН) №4'));
});

test('a Latin query that finds nothing suggests the Cyrillic product names', function () {
    config(['prices.search.similarity_threshold' => 0.95]);
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Аспирин таб 500мг №10', 5);

    $this->get(route('home', ['q' => 'aspirn']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.groups', [])
            ->where('suggestions', ['аспирин'])
        );
});

test('a synonym finds the drug under its other name, in both directions', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Naz ada', 'Ацетилсалициловая кислота таб 500мг №10', 1.6);
    offer($upload, 'Merdan Gos', 'Аспирин 500мг таблетки №10', 3.0);
    DrugSynonym::factory()->create(['term_normalized' => 'аспирин', 'synonym_normalized' => 'ацетилсалициловая кислота']);

    foreach (['аспирин', 'ацетилсалициловая кислота'] as $query) {
        $this->get(route('home', ['q' => $query]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('result.groups.0.offers', 2)
                ->where('result.groups.0.offers.0.pharmacy.name', 'Naz ada')
                ->where('result.groups.0.offers.1.pharmacy.name', 'Merdan Gos')
            );
    }
});

test('synonyms reach the names linked through another name', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Аскофен-П таб №10', 2.6);
    DrugSynonym::factory()->create(['term_normalized' => 'action plus', 'synonym_normalized' => 'цитрамон']);
    DrugSynonym::factory()->create(['term_normalized' => 'цитрамон', 'synonym_normalized' => 'аскофен п']);

    $this->get(route('home', ['q' => 'action plus']))
        ->assertInertia(fn (Assert $page) => $page->where('result.groups.0.offers.0.raw_name', 'Аскофен-П таб №10'));
});

test('a search that finds nothing suggests close product names', function () {
    config(['prices.search.similarity_threshold' => 0.95]);
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Аспирин таб 500мг №10', 5);

    $this->get(route('home', ['q' => 'аспирн']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.groups', [])
            ->where('suggestions', ['аспирин'])
        );
});

test('nothing is searched before prices are published', function () {
    offer(PriceUpload::factory()->create(['status' => PriceUploadStatus::NeedsReview]), 'Kamat', 'Цитрамон таб №6', 1.5);

    $this->get(route('home', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pricesDate', null)
            ->where('result.groups', [])
            ->where('suggestions', [])
        );
});

test('a query shorter than two letters is refused with a localized message, spaces not counting', function (string $query) {
    $this->get(route('home', ['q' => $query]))
        ->assertSessionHasErrors(['q' => 'Введите хотя бы 2 буквы названия препарата.']);
})->with(['p', '  ц  ']);

test('a query longer than 100 characters is refused with a localized message', function () {
    $this->get(route('home', ['q' => str_repeat('а', 101)]))
        ->assertSessionHasErrors(['q' => 'Поле «Название препарата» не должно превышать 100 символов.']);
});

test('variants of a drug with another dose or dosage form are separate groups with readable labels', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Азитромицин таб 500мг №3', 12);
    offer($upload, 'ASDS', 'Азитромицин Сандоз 500мг таблетки №3', 13);
    offer($upload, 'Gumry', 'Азитромицин Лек 200мг/5мл суспензия 200мл', 30);

    $this->get(route('home', ['q' => 'азитромицин']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('result.groups', 2)
            ->where('result.groups.0.drug_name', 'Азитромицин')
            ->where('result.groups.0.dosage_form_label', 'таблетки')
            ->where('result.groups.0.dose_label', '500 мг')
            ->where('result.groups.0.pack_count', 3)
            ->where('result.groups.0.pharmacies_count', 2)
            ->where('result.groups.0.offers.1.price_difference', '1.00')
            ->where('result.groups.1.dosage_form_label', 'суспензия')
            ->where('result.groups.1.dose_label', '200 мг, 5 мл, 200 мл')
            ->where('result.summary', ['variants_count' => 2, 'pharmacies_count' => 3, 'lowest_price' => '12.00'])
            ->where('result.corrected_to', null)
        );
});

test('labels follow the interface language', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Азитромицин таб 500мг №3', 12);

    $this->withSession(['locale' => 'tk'])
        ->get(route('home', ['q' => 'азитромицин']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.groups.0.dosage_form_label', 'tabletkalar')
            ->where('result.groups.0.dose_label', '500 mg')
        );
});

test('a dosage form named in the query shows only that form', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Азитромицин таб 500мг №3', 12);
    offer($upload, 'Gumry', 'Азитромицин Лек 200мг/5мл суспензия 200мл', 30);

    $this->get(route('home', ['q' => 'азитромицин суспензия']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('result.groups', 1)
            ->where('result.groups.0.dosage_form', 'suspension')
        );
});

test('a misspelt query says which drug the results are for', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'ASDS', 'Анальгин таб 500мг №10 Беларусь', 3.1);

    $this->get(route('home', ['q' => 'анолгин']))
        ->assertInertia(fn (Assert $page) => $page->where('result.corrected_to', 'Аналгин'));
});

test('the empty search page tells how many pharmacies the prices cover and offers popular queries', function () {
    $upload = PriceUpload::factory()->active()->create(['activated_at' => now()->subDays(2)]);
    offer($upload, 'Kamat', 'Цитрамон таб №6', 1.5);
    offer($upload, 'ASDS', 'Цитрамон таб №6', 1.4);
    offer($upload, 'ASDS', 'Аналгин таб №10', 2.1);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pharmaciesCount', 2)
            ->where('pricesStale', false)
            ->where('minQueryLength', 2)
            ->where('popularQueries', config('prices.search.popular'))
            ->where('result.summary', null)
        );
});

test('prices published more than a week ago are flagged as stale', function () {
    PriceUpload::factory()->active()->create(['activated_at' => now()->subDays(8)]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('pricesStale', true));
});

test('each offer shows the pharmacy phones in one format and its address', function () {
    $upload = PriceUpload::factory()->active()->create();
    Pharmacy::factory()->create(['name' => 'Altyn Dag', 'phones' => ['+99312229830', '+99365974444'], 'address' => 'ул. Огузхан, дом 106']);
    offer($upload, 'Altyn Dag', 'Цитрамон таб №6', 1.5);

    $this->get(route('home', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.groups.0.offers.0.pharmacy', [
                'name' => 'Altyn Dag',
                'phones' => [
                    ['display' => '+993 12 22 98 30', 'dial' => '+99312229830'],
                    ['display' => '+993 65 97 44 44', 'dial' => '+99365974444'],
                ],
                'address' => 'ул. Огузхан, дом 106',
            ])
        );
});
