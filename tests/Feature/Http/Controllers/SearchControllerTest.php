<?php

use App\Enums\PriceUploadStatus;
use App\Models\DiscountRule;
use App\Models\Pharmacy;
use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Services\Prices\Parsing\NameNormalizer;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A product of a pharmacy's sheet, normalized the way the import normalizes it.
 *
 * @param  array<string, mixed>  $attributes
 */
function offer(PriceUpload $upload, string $pharmacy, string $name, float $price, array $attributes = []): PriceItem
{
    $normalized = (new NameNormalizer)->normalize($name);

    return PriceItem::factory()
        ->for($upload)
        ->for(Pharmacy::query()->firstOrCreate(['name' => $pharmacy]))
        ->create([
            'raw_name' => $name,
            'name_normalized' => $normalized->normalized,
            'name_skeleton' => $normalized->skeleton,
            'base_name' => $normalized->baseName,
            'name_compact' => str_replace(' ', '', $normalized->baseName),
            'active_ingredient' => $normalized->activeIngredient,
            'pack_count' => $normalized->packCount,
            'dose' => $normalized->dose,
            'price' => $price,
            'price_discounted' => null,
            'effective_price' => $price,
            'goods_discount_percent' => null,
            ...$attributes,
        ]);
}

test('the search page opens without signing in', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('query', '')
            ->where('groups', [])
            ->where('pricesDate', null)
        );
});

test('a typo in the vowels still finds the drug', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'ASDS', 'Анальгин таб 500мг №10 Беларусь', 3.1);

    $this->get(route('home', ['q' => 'анолгин']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('groups.0.offers.0.raw_name', 'Анальгин таб 500мг №10 Беларусь')
            ->where('groups.0.offers.0.pharmacy.name', 'ASDS')
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
            ->has('groups.0.offers', 1)
            ->where('groups.0.offers.0.raw_name', 'Цитрамон таб №4')
        );

    $this->get(route('home', ['q' => 'аналгин']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups.0.offers', 1)
            ->where('groups.0.offers.0.raw_name', 'Анальгин таб №10')
        );
});

test('a name typed without its hyphen is found', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Atajan Gos', 'Но-шпа ампула №5', 29.7);

    $this->get(route('home', ['q' => 'ношпа']))
        ->assertInertia(fn (Assert $page) => $page->where('groups.0.offers.0.raw_name', 'Но-шпа ампула №5'));
});

test('offers are grouped by pack size because only the same pack can be compared', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Цитрамон П №10', 2.6);
    offer($upload, 'Gumry', 'Цитрамон-Боримед таб №6', 1.5);

    $this->get(route('home', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 2)
            ->where('groups.0.pack_count', 6)
            ->where('groups.0.offers.0.price', '1.50')
            ->where('groups.1.pack_count', 10)
            ->where('groups.1.offers.0.price', '2.60')
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
            ->has('groups', 1)
            ->has('groups.0.offers', 3)
            ->where('groups.0.offers.0.pharmacy.name', 'Kamat')
            ->where('groups.0.offers.0.price', '1.10')
            ->where('groups.0.offers.0.place', 1)
            ->where('groups.0.offers.1.pharmacy.name', 'ASDS')
            ->where('groups.0.offers.2.pharmacy.name', 'Enejan')
            ->where('groups.0.offers.2.place', 3)
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
            ->where('groups.0.offers.0.pharmacy.name', 'Rahmet')
            ->where('groups.0.offers.0.effective_price', '90.00')
            ->where('groups.0.offers.0.price', '100.00')
            ->where('groups.0.offers.1.pharmacy.name', 'Ashgabat')
            ->where('groups.0.offers.1.sheet_discounts.0.min_sum', '10000.00')
            ->where('groups.0.offers.0.sheet_discounts', [])
        );
});

test('only the active upload is searched', function () {
    offer(PriceUpload::factory()->archived()->create(), 'Old', 'Цитрамон таб №6', 0.5);
    offer(PriceUpload::factory()->create(['status' => PriceUploadStatus::NeedsReview]), 'Next', 'Цитрамон таб №6', 0.7);
    offer(PriceUpload::factory()->active()->create(), 'Kamat', 'Цитрамон таб №6', 1.5);

    $this->get(route('home', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups.0.offers', 1)
            ->where('groups.0.offers.0.pharmacy.name', 'Kamat')
        );
});

test('a pack size in the query narrows the search to that pack', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Цитрамон П №10', 2.6);
    offer($upload, 'Gumry', 'Цитрамон таб №6', 1.5);

    $this->get(route('home', ['q' => 'цитрамон №10']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 1)
            ->where('groups.0.pack_count', 10)
        );
});

test('the active ingredient finds the drug under another brand name', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Abray Umyt', 'ACTION PLUS (ЦИТРАМОН) №4', 1.1);

    $this->get(route('home', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page->where('groups.0.offers.0.raw_name', 'ACTION PLUS (ЦИТРАМОН) №4'));
});

test('a search that finds nothing suggests close product names', function () {
    config(['prices.search.similarity_threshold' => 0.95]);
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Аспирин таб 500мг №10', 5);

    $this->get(route('home', ['q' => 'аспирн']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('groups', [])
            ->where('suggestions', ['аспирин'])
        );
});

test('nothing is searched before prices are published', function () {
    offer(PriceUpload::factory()->create(['status' => PriceUploadStatus::NeedsReview]), 'Kamat', 'Цитрамон таб №6', 1.5);

    $this->get(route('home', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pricesDate', null)
            ->where('groups', [])
            ->where('suggestions', [])
        );
});

test('a query longer than 100 characters is refused with a localized message', function () {
    $this->get(route('home', ['q' => str_repeat('а', 101)]))
        ->assertSessionHasErrors(['q' => 'Поле «Название препарата» не должно превышать 100 символов.']);
});
