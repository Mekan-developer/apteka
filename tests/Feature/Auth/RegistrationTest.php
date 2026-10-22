<?php

use App\Models\User;

// Accounts are created by the super admin, so nobody can sign themselves up.

test('the registration screen does not exist', function () {
    $this->get('/register')->assertNotFound();
});

test('nobody can register an account', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});
