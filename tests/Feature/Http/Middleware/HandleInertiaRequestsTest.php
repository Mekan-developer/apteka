<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a notify flash from the previous request reaches the page as inertia flash data', function () {
    $user = User::factory()->create();
    $notification = ['type' => 'warning', 'message' => 'Проверьте данные.'];

    $response = $this
        ->actingAs($user)
        ->withSession(['notify' => $notification])
        ->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page->hasFlash('notify', $notification));
});

test('pages without a notification carry no notify flash', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page->missingFlash('notify'));
});

test('pages share the current locale with its frontend translations', function () {
    $user = User::factory()->create();
    $translations = json_decode(file_get_contents(lang_path('ru.json')), true);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('locale', 'ru')
        ->where('availableLocales', ['ru', 'tk'])
        ->where('translations', $translations)
    );
});
