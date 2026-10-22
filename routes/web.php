<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PriceUploadController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::put('locale', LocaleController::class)->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('price-uploads', PriceUploadController::class)
        ->only(['index', 'store', 'show'])
        ->middleware('can:manage-prices');

    Route::middleware('can:manage-admins')->group(function () {
        Route::get('admins', [AdminController::class, 'index'])->name('admins.index');
        Route::post('admins', [AdminController::class, 'store'])->name('admins.store');

        Route::middleware('can:manage-admin,admin')->group(function () {
            Route::patch('admins/{admin}/status', [AdminController::class, 'toggleStatus'])->name('admins.toggle-status');
            Route::delete('admins/{admin}', [AdminController::class, 'destroy'])->name('admins.destroy');
        });
    });
});

require __DIR__.'/settings.php';
