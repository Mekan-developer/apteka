<?php

use App\Models\Pharmacy;
use App\Models\PharmacySheetMapping;
use Illuminate\Database\UniqueConstraintViolationException;

test('two pharmacies cannot have the same name', function () {
    Pharmacy::factory()->create(['name' => 'Kamat']);

    Pharmacy::factory()->create(['name' => 'Kamat']);
})->throws(UniqueConstraintViolationException::class);

test('a pharmacy keeps a single remembered sheet mapping', function () {
    $pharmacy = Pharmacy::factory()->create();
    PharmacySheetMapping::factory()->for($pharmacy)->create();

    PharmacySheetMapping::factory()->for($pharmacy)->create();
})->throws(UniqueConstraintViolationException::class);

test('deleting a pharmacy deletes its remembered sheet mapping', function () {
    $mapping = PharmacySheetMapping::factory()->create();

    $mapping->pharmacy->delete();

    expect($mapping->fresh())->toBeNull();
});
