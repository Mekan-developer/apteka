<?php

use App\Http\Controllers\Api\V1\DrugSuggestionController;
use App\Http\Controllers\Api\V1\PingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Loaded from bootstrap/app.php with the "api" middleware, the "/api/v1"
| prefix and the "api.v1." name prefix. Do not rename or remove fields of
| existing v1 responses: breaking changes go to a new version (v2).
|
*/

Route::get('ping', PingController::class)->name('ping');

Route::get('drug-suggestions', DrugSuggestionController::class)->middleware('throttle:drug-suggestions')->name('drug-suggestions');
