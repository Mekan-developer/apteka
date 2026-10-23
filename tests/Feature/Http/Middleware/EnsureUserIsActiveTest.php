<?php

use App\Models\User;

test('a deactivated admin is signed out with an explanation', function () {
    $admin = User::factory()->inactive()->create();

    $this->actingAs($admin)
        ->get(route('price-uploads.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('notify', [
            'type' => 'error',
            'message' => 'Ваша учётная запись отключена. Обратитесь к суперадминистратору.',
        ]);

    $this->assertGuest();
});

test('a deactivated admin who enters the right password does not stay signed in', function () {
    $admin = User::factory()->inactive()->create();

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password']);
    $this->get(route('price-uploads.index'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an active admin keeps working', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('price-uploads.index'))
        ->assertOk();
});
