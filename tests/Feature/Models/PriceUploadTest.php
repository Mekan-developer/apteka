<?php

use App\Enums\PriceUploadStatus;
use App\Models\DiscountRule;
use App\Models\Pharmacy;
use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

test('a second active upload is refused by the database', function () {
    PriceUpload::factory()->active()->create();

    PriceUpload::factory()->active()->create();
})->throws(UniqueConstraintViolationException::class);

test('archived uploads are kept next to the active one', function () {
    PriceUpload::factory()->archived()->count(2)->create();
    PriceUpload::factory()->active()->create();

    expect(PriceUpload::query()->where('status', PriceUploadStatus::Archived)->count())->toBe(2)
        ->and(PriceUpload::query()->where('status', PriceUploadStatus::Active)->count())->toBe(1);
});

test('deleting an upload deletes its sheets, items and discount rules but keeps the pharmacy', function () {
    $pharmacy = Pharmacy::factory()->create();
    $upload = PriceUpload::factory()->create();
    PriceUploadSheet::factory()->for($upload)->for($pharmacy)->create();
    PriceItem::factory()->for($upload)->for($pharmacy)->count(3)->create();
    DiscountRule::factory()->for($upload)->for($pharmacy)->create();

    $upload->delete();

    expect(PriceUploadSheet::query()->count())->toBe(0)
        ->and(PriceItem::query()->count())->toBe(0)
        ->and(DiscountRule::query()->count())->toBe(0)
        ->and($pharmacy->fresh())->not->toBeNull();
});

test('deleting the user who uploaded the file keeps the upload in history', function () {
    $user = User::factory()->create();
    $upload = PriceUpload::factory()->for($user)->create();

    $user->delete();

    expect($upload->fresh()->user_id)->toBeNull();
});

test('status and activation time are read back as an enum and a date', function () {
    $upload = PriceUpload::factory()->active()->create()->fresh();

    expect($upload->status)->toBe(PriceUploadStatus::Active)
        ->and($upload->activated_at)->toBeInstanceOf(DateTimeInterface::class);
});
