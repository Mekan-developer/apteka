<?php

use App\Enums\PriceUploadStatus;
use App\Models\PriceUpload;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are sent to the login page', function () {
    $this->get(route('price-history'))->assertRedirect(route('login'));
});

test('shows the cheapest price of every pack size in each published upload, newest upload first', function () {
    $older = PriceUpload::factory()->archived()->create(['activated_at' => now()->subWeek()]);
    $newer = PriceUpload::factory()->active()->create(['activated_at' => now()]);
    offer($older, 'Kamat', 'Цитрамон таб №6', 1.5);
    offer($older, 'ASDS', 'Цитрамон таб №6', 1.3);
    offer($newer, 'Kamat', 'Цитрамон таб №6', 1.2);
    offer($newer, 'Gumry', 'Цитрамон П №10', 2.6);

    $this->actingAs(User::factory()->create())
        ->get(route('price-history', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('price-history/Index')
            ->where('uploads.0.id', $newer->id)
            ->where('uploads.1.id', $older->id)
            ->has('rows', 2)
            ->where('rows.0.pack_count', 6)
            ->where('rows.0.prices', fn ($prices): bool => collect($prices)->mapWithKeys(fn (array $price): array => [
                $price['upload_id'] => [$price['offer']['effective_price'], $price['offer']['pharmacy']['name']],
            ])->all() == [$older->id => ['1.30', 'ASDS'], $newer->id => ['1.20', 'Kamat']])
            ->where('rows.1.pack_count', 10)
            ->where('rows.1.prices', fn ($prices): bool => collect($prices)->pluck('upload_id')->all() === [$newer->id])
        );
});

test('uploads that were never published are not part of the history', function () {
    $published = PriceUpload::factory()->active()->create();
    $unpublished = PriceUpload::factory()->create(['status' => PriceUploadStatus::NeedsReview]);
    offer($published, 'Kamat', 'Цитрамон таб №6', 1.5);
    offer($unpublished, 'Kamat', 'Цитрамон таб №6', 0.9);

    $this->actingAs(User::factory()->create())
        ->get(route('price-history', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('uploads', 1)
            ->where('uploads.0.id', $published->id)
            ->has('rows.0.prices', 1)
            ->where('rows.0.prices.0.upload_id', $published->id)
            ->where('rows.0.prices.0.offer.effective_price', '1.50')
        );
});

test('the history shows only as many uploads as configured', function () {
    config(['prices.history.uploads' => 2]);
    PriceUpload::factory()->archived()->count(2)->sequence(
        ['activated_at' => now()->subWeeks(3)],
        ['activated_at' => now()->subWeeks(2)],
    )->create();
    PriceUpload::factory()->active()->create(['activated_at' => now()]);

    $this->actingAs(User::factory()->create())
        ->get(route('price-history', ['q' => 'цитрамон']))
        ->assertInertia(fn (Assert $page) => $page->has('uploads', 2));
});

test('the page opens without a query', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('price-history'))
        ->assertInertia(fn (Assert $page) => $page->where('query', '')->where('rows', [])->where('uploads', []));
});
