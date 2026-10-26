<?php

use App\Enums\DosageForm;
use App\Jobs\RefreshPriceUploadJob;
use App\Models\Pharmacy;
use App\Models\PriceUpload;
use Illuminate\Support\Facades\Queue;

test('products imported before the dosage form was read get it, and running again changes nothing', function () {
    $upload = PriceUpload::factory()->active()->create();
    $item = offer($upload, 'Kamat', 'Азитромицин Лек 200мг/5мл суспензия 200мл', 30, ['dosage_form' => null, 'base_name' => 'старое']);

    RefreshPriceUploadJob::dispatchSync($upload);
    RefreshPriceUploadJob::dispatchSync($upload);

    expect($item->refresh()->dosage_form)->toBe(DosageForm::Suspension)
        ->and($item->base_name)->toBe('азитромицин лек')
        ->and($item->dose)->toBe('200мг 5мл 200мл');
});

test('the active upload gives the pharmacies the contacts from their sheets', function () {
    $upload = uploadWithSheets([
        'Kamat' => [['ПРАЙС ЛИСТ'], ['99364320482'], ['Наименование', 'Цена'], ['Цитрамон таб №6', 1.5]],
    ]);
    $upload->update(['status' => 'active', 'activated_at' => now()]);
    Pharmacy::factory()->create(['name' => 'Kamat', 'phones' => null, 'address' => null]);

    RefreshPriceUploadJob::dispatchSync($upload);

    expect(Pharmacy::query()->where('name', 'Kamat')->sole()->phones)->toBe(['+99364320482']);
});

test('an archived upload does not overwrite the current contacts', function () {
    $upload = uploadWithSheets([
        'Kamat' => [['99364320482'], ['Наименование', 'Цена'], ['Цитрамон таб №6', 1.5]],
    ]);
    $upload->update(['status' => 'archived', 'activated_at' => now()->subWeek()]);
    Pharmacy::factory()->create(['name' => 'Kamat', 'phones' => ['+99365111111']]);

    RefreshPriceUploadJob::dispatchSync($upload);

    expect(Pharmacy::query()->where('name', 'Kamat')->sole()->phones)->toBe(['+99365111111']);
});

test('the refresh command queues every upload that holds products', function () {
    Queue::fake();
    $active = PriceUpload::factory()->active()->create();
    $archived = PriceUpload::factory()->archived()->create();
    PriceUpload::factory()->create(['status' => 'failed']);

    $this->artisan('prices:refresh')
        ->expectsOutput('Загрузок поставлено в очередь на обновление: 2.')
        ->assertSuccessful();

    Queue::assertPushed(RefreshPriceUploadJob::class, 2);
    Queue::assertPushed(RefreshPriceUploadJob::class, fn (RefreshPriceUploadJob $job): bool => $job->priceUpload->is($active));
    Queue::assertPushed(RefreshPriceUploadJob::class, fn (RefreshPriceUploadJob $job): bool => $job->priceUpload->is($archived));
});
