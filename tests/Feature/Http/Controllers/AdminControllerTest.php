<?php

use App\Enums\UserRole;
use App\Models\PriceUpload;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('the super admin sees every account and may manage only the admins', function () {
    $superAdmin = User::factory()->superAdmin()->create(['name' => 'Bahar']);
    User::factory()->create(['name' => 'Aman']);

    $this->actingAs($superAdmin)
        ->get(route('admins.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admins/Index')
            ->has('admins', 2)
            ->where('admins.0.name', 'Bahar')
            ->where('admins.0.role', 'super_admin')
            ->where('admins.0.can_manage', false)
            ->where('admins.1.name', 'Aman')
            ->where('admins.1.can_manage', true)
        );
});

test('an admin has no access to managing admins', function (string $method, Closure $url) {
    $admin = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($admin)->{$method}($url($other))->assertForbidden();
})->with([
    'list' => ['get', fn () => route('admins.index')],
    'create' => ['post', fn () => route('admins.store')],
    'deactivate' => ['patch', fn (User $other) => route('admins.toggle-status', $other)],
    'delete' => ['delete', fn (User $other) => route('admins.destroy', $other)],
]);

test('the super admin creates an admin who can sign in right away', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($superAdmin)->post(route('admins.store'), [
        'name' => 'Aman Ataýew',
        'email' => 'aman@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $admin = User::query()->where('email', 'aman@example.com')->sole();

    $response
        ->assertRedirect(route('admins.index'))
        ->assertSessionHas('notify', [
            'type' => 'success',
            'message' => 'Администратор «Aman Ataýew» создан. Он может войти со своим email и паролем.',
        ]);

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secret-password', $admin->password))->toBeTrue();
});

test('an admin is not created with an e-mail that is already taken', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    User::factory()->create(['email' => 'aman@example.com']);

    $this->actingAs($superAdmin)
        ->post(route('admins.store'), [
            'name' => 'Aman',
            'email' => 'aman@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
        ->assertSessionHasErrors(['email' => 'Такое значение поля «Email» уже занято.']);

    expect(User::query()->count())->toBe(2);
});

test('an admin is not created when the password confirmation differs', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('admins.store'), [
            'name' => 'Aman',
            'email' => 'aman@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'another-password',
        ])
        ->assertSessionHasErrors(['password' => 'Поле «Пароль» не совпадает с подтверждением.']);
});

test('the super admin deactivates an admin and activates them again', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->create(['name' => 'Aman']);

    $this->actingAs($superAdmin)
        ->patch(route('admins.toggle-status', $admin))
        ->assertRedirect(route('admins.index'))
        ->assertSessionHas('notify', ['type' => 'success', 'message' => 'Администратор «Aman» отключён и больше не может войти.']);

    expect($admin->refresh()->is_active)->toBeFalse();

    $this->patch(route('admins.toggle-status', $admin))
        ->assertSessionHas('notify', ['type' => 'success', 'message' => 'Администратор «Aman» снова может входить в систему.']);

    expect($admin->refresh()->is_active)->toBeTrue();
});

test('the super admin deletes an admin and their price uploads stay in history', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->create(['name' => 'Aman']);
    $upload = PriceUpload::factory()->for($admin)->create();

    $this->actingAs($superAdmin)
        ->delete(route('admins.destroy', $admin))
        ->assertRedirect(route('admins.index'))
        ->assertSessionHas('notify', ['type' => 'success', 'message' => 'Администратор «Aman» удалён.']);

    expect($admin->fresh())->toBeNull()
        ->and($upload->refresh()->user_id)->toBeNull();
});

test('the super admin cannot deactivate or delete a super admin account, their own included', function (string $method, string $route) {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)->{$method}(route($route, $superAdmin))->assertForbidden();

    expect($superAdmin->refresh()->is_active)->toBeTrue();
})->with([
    'deactivate' => ['patch', 'admins.toggle-status'],
    'delete' => ['delete', 'admins.destroy'],
]);

test('only the super admin is told they may manage admins', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('price-uploads.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.manageAdmins', false));

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('price-uploads.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.manageAdmins', true));
});
