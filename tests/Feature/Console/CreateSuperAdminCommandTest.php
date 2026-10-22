<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('creates a super admin who can sign in right away', function () {
    $this->artisan('admins:create-super-admin', [
        '--name' => 'Bahar',
        '--email' => 'bahar@example.com',
        '--password' => 'secret-password',
    ])
        ->expectsOutputToContain('Суперадминистратор «bahar@example.com» создан.')
        ->assertSuccessful();

    $superAdmin = User::query()->sole();

    expect($superAdmin->role)->toBe(UserRole::SuperAdmin)
        ->and($superAdmin->is_active)->toBeTrue()
        ->and($superAdmin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secret-password', $superAdmin->password))->toBeTrue();
});

test('asks for whatever was not given as an option', function () {
    $this->artisan('admins:create-super-admin')
        ->expectsQuestion('Имя', 'Bahar')
        ->expectsQuestion('Email', 'bahar@example.com')
        ->expectsQuestion('Пароль', 'secret-password')
        ->assertSuccessful();

    expect(User::query()->sole()->email)->toBe('bahar@example.com');
});

test('refuses an e-mail that is already taken', function () {
    User::factory()->create(['email' => 'bahar@example.com']);

    $this->artisan('admins:create-super-admin', [
        '--name' => 'Bahar',
        '--email' => 'bahar@example.com',
        '--password' => 'secret-password',
    ])
        ->expectsOutputToContain('Такое значение поля «Email» уже занято.')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});
