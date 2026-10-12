<?php

use App\Models\User;
use Illuminate\Database\LazyLoadingViolationException;

test('lazy loading a relation outside production throws', function () {
    User::factory()->count(2)->create();

    $user = User::query()->get()->first();

    $user->notifications;
})->throws(LazyLoadingViolationException::class);
