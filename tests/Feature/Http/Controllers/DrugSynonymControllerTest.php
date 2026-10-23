<?php

use App\Models\DrugSynonym;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins see the names the search treats as one drug', function () {
    DrugSynonym::factory()->create(['term' => 'Аспирин', 'synonym' => 'Ацетилсалициловая кислота']);

    $this->actingAs(User::factory()->create())
        ->get(route('synonyms.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('synonyms/Index')
            ->where('synonyms.0.term', 'Аспирин')
            ->where('synonyms.0.synonym', 'Ацетилсалициловая кислота')
        );
});

test('guests are sent to the login page', function () {
    $this->get(route('synonyms.index'))->assertRedirect(route('login'));
});

test('linking two names keeps them as typed and as the search compares them', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('synonyms.store'), ['term' => 'ACTION PLUS', 'synonym' => 'Цитрамон таб.'])
        ->assertRedirect(route('synonyms.index'))
        ->assertSessionHas('notify', [
            'type' => 'success',
            'message' => 'Теперь поиск «ACTION PLUS» находит и «Цитрамон таб.», и наоборот.',
        ]);

    $synonym = DrugSynonym::query()->sole();

    expect([$synonym->term_normalized, $synonym->synonym_normalized])->toBe(['action plus', 'цитрамон']);
});

test('a name cannot be linked to itself', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('synonyms.store'), ['term' => 'Аспирин', 'synonym' => 'аспирин таб.'])
        ->assertSessionHasErrors(['synonym' => 'Это одно и то же название. Укажите другое название того же препарата.']);

    expect(DrugSynonym::query()->count())->toBe(0);
});

test('names that are already linked are not linked again, in either order', function () {
    DrugSynonym::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('synonyms.store'), ['term' => 'Ацетилсалициловая кислота', 'synonym' => 'Аспирин'])
        ->assertSessionHasErrors(['synonym' => 'Эти названия уже связаны.']);

    expect(DrugSynonym::query()->count())->toBe(1);
});

test('both names are required', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('synonyms.store'), ['term' => 'Аспирин'])
        ->assertSessionHasErrors(['synonym' => 'Поле «Другое название» обязательно для заполнения.']);
});

test('a link between names can be removed', function () {
    $synonym = DrugSynonym::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('synonyms.destroy', $synonym))
        ->assertRedirect(route('synonyms.index'))
        ->assertSessionHas('notify', ['type' => 'success', 'message' => 'Связь «Аспирин» = «Ацетилсалициловая кислота» удалена.']);

    expect($synonym->fresh())->toBeNull();
});
