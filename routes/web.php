<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PriceUploadController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::put('locale', LocaleController::class)->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('price-uploads', PriceUploadController::class)->only(['index', 'store', 'show']);
});

require __DIR__.'/settings.php';
