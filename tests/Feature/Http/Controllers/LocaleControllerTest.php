<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('switching to turkmen translates the following pages and notifications', function () {
    $user = User::factory()->create();
    $translations = json_decode(file_get_contents(lang_path('tk.json')), true);

    $response = $this
        ->actingAs($user)
        ->from(route('price-uploads.index'))
        ->put(route('locale.update'), ['locale' => 'tk']);

    $response
        ->assertRedirect(route('price-uploads.index'))
        ->assertSessionHas('notify', [
            'type' => 'success',
            'message' => 'Interfeýsiň dili türkmen diline üýtgedildi.',
        ]);

    $this->get(route('price-uploads.index'))->assertInertia(fn (Assert $page) => $page
        ->where('locale', 'tk')
        ->where('translations', $translations)
    );
});

test('an unsupported locale is rejected with a localized message', function () {
    $response = $this
        ->from(route('home'))
        ->put(route('locale.update'), ['locale' => 'en']);

    $response
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors(['locale' => 'Выбрано недопустимое значение поля «Язык».']);

    expect(session('locale'))->toBeNull();
});
