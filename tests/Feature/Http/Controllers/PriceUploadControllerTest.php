<?php

use App\Enums\PriceUploadSheetStatus;
use App\Enums\PriceUploadStatus;
use App\Jobs\ImportPriceUploadJob;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const XLSX_MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

test('guests are sent to the login page', function () {
    $this->get(route('price-uploads.index'))->assertRedirect(route('login'));
    $this->post(route('price-uploads.store'))->assertRedirect(route('login'));
});

test('both admins and the super admin work with price uploads', function (User $user) {
    $this->actingAs($user)->get(route('price-uploads.index'))->assertOk();
})->with([
    'admin' => fn () => User::factory()->create(),
    'super admin' => fn () => User::factory()->superAdmin()->create(),
]);

test('the page lists uploads newest first with their import progress', function () {
    PriceUpload::factory()->archived()->create(['original_filename' => 'prices-15.10.xlsx']);
    $newest = PriceUpload::factory()->create(['original_filename' => 'prices-22.10.xlsx', 'sheets_total' => 98]);
    PriceUploadSheet::factory()->for($newest)->count(2)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('price-uploads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('price-uploads/Index')
            ->where('uploads.data.0.original_filename', 'prices-22.10.xlsx')
            ->where('uploads.data.0.sheets_processed', 2)
            ->where('uploads.data.0.sheets_total', 98)
            ->where('uploads.data.1.original_filename', 'prices-15.10.xlsx')
            ->where('maxUploadMegabytes', 40)
        );
});

test('uploading a price file keeps it and queues its import', function () {
    Queue::fake();
    Storage::fake('local');
    $user = User::factory()->create();
    $path = xlsxFile(['Ashgabat' => [['Наименование', 'Цена'], ['Бетасерк 16мг таб №30', '96,00']]]);

    $response = $this->actingAs($user)->post(route('price-uploads.store'), [
        'file' => new UploadedFile($path, 'prices-22.10.xlsx', null, null, true),
    ]);

    $upload = PriceUpload::query()->sole();

    $response
        ->assertRedirect(route('price-uploads.show', $upload))
        ->assertSessionHas('notify', [
            'type' => 'success',
            'message' => 'Файл «prices-22.10.xlsx» загружен. Разбираем листы, обычно это занимает до минуты.',
        ]);

    expect($upload->user_id)->toBe($user->id)
        ->and($upload->original_filename)->toBe('prices-22.10.xlsx')
        ->and($upload->status)->toBe(PriceUploadStatus::Uploaded);

    Storage::disk('local')->assertExists($upload->stored_path);
    Queue::assertPushed(ImportPriceUploadJob::class, fn (ImportPriceUploadJob $job): bool => $job->priceUpload->is($upload));
});

test('an upload without a file is refused with a localized message', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('price-uploads.store'))
        ->assertSessionHasErrors(['file' => 'Поле «Файл с прайсами» обязательно для заполнения.']);

    Queue::assertNothingPushed();
});

test('only an xlsx file is accepted', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('price-uploads.store'), ['file' => UploadedFile::fake()->create('prices.csv', 10, 'text/csv')])
        ->assertSessionHasErrors(['file' => 'Файл «Файл с прайсами» должен быть одного из типов: xlsx.']);

    Queue::assertNothingPushed();
});

test('a file above the size limit is refused', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('price-uploads.store'), ['file' => UploadedFile::fake()->create('prices.xlsx', 40961, XLSX_MIME_TYPE)])
        ->assertSessionHasErrors(['file' => 'Размер файла «Файл с прайсами» не должен превышать 40960 КБ.']);

    Queue::assertNothingPushed();
});

test('the upload page shows the import progress and the result of every sheet in workbook order', function () {
    $upload = PriceUpload::factory()->create(['status' => PriceUploadStatus::Processing, 'sheets_total' => 98]);
    PriceUploadSheet::factory()->for($upload)->needsReview()->create([
        'sheet_name' => 'Sirin Nabat',
        'sheet_index' => 29,
        'error' => 'few_prices',
    ]);
    PriceUploadSheet::factory()->for($upload)->create(['sheet_name' => 'Kamat', 'sheet_index' => 0, 'rows_parsed' => 586]);

    $this->actingAs(User::factory()->create())
        ->get(route('price-uploads.show', $upload))
        ->assertInertia(fn (Assert $page) => $page
            ->component('price-uploads/Show')
            ->where('upload.status', 'processing')
            ->where('upload.sheets_processed', 2)
            ->where('upload.sheets.0.sheet_name', 'Kamat')
            ->where('upload.sheets.0.rows_parsed', 586)
            ->where('upload.sheets.0.review_reason', null)
            ->where('upload.sheets.1.sheet_name', 'Sirin Nabat')
            ->where('upload.sheets.1.review_reason', 'few_prices')
        );
});

test('activating an upload makes search use it and moves the previous one to history', function () {
    $previous = PriceUpload::factory()->active()->create();
    $upload = PriceUpload::factory()->create(['status' => PriceUploadStatus::NeedsReview, 'original_filename' => 'prices-22.10.xlsx']);

    $this->actingAs(User::factory()->create())
        ->post(route('price-uploads.activate', $upload))
        ->assertRedirect(route('price-uploads.show', $upload))
        ->assertSessionHas('notify', [
            'type' => 'success',
            'message' => 'Прайс «prices-22.10.xlsx» активирован. Поиск теперь работает по нему, предыдущий прайс перенесён в архив.',
        ]);

    expect($upload->refresh()->status)->toBe(PriceUploadStatus::Active)
        ->and($upload->activated_at)->not->toBeNull()
        ->and($previous->refresh()->status)->toBe(PriceUploadStatus::Archived);
});

test('an archived upload can be activated again to roll back', function () {
    $current = PriceUpload::factory()->active()->create();
    $archived = PriceUpload::factory()->archived()->create();

    $this->actingAs(User::factory()->create())->post(route('price-uploads.activate', $archived));

    expect($archived->refresh()->status)->toBe(PriceUploadStatus::Active)
        ->and($current->refresh()->status)->toBe(PriceUploadStatus::Archived);
});

test('an upload that is not ready is not activated', function (PriceUploadStatus $status) {
    $active = PriceUpload::factory()->active()->create();
    $upload = PriceUpload::factory()->create(['status' => $status]);

    $this->actingAs(User::factory()->create())
        ->post(route('price-uploads.activate', $upload))
        ->assertSessionHas('notify', [
            'type' => 'error',
            'message' => 'Этот прайс нельзя активировать: он ещё разбирается, уже активен или его не удалось разобрать.',
        ]);

    expect($upload->refresh()->status)->toBe($status)
        ->and($active->refresh()->status)->toBe(PriceUploadStatus::Active);
})->with([
    'queued' => PriceUploadStatus::Uploaded,
    'being imported' => PriceUploadStatus::Processing,
    'failed' => PriceUploadStatus::Failed,
]);

test('an upload is not activated while one of its sheets is being parsed again', function () {
    $upload = PriceUpload::factory()->create(['status' => PriceUploadStatus::NeedsReview]);
    PriceUploadSheet::factory()->for($upload)->create(['status' => PriceUploadSheetStatus::Processing]);

    $this->actingAs(User::factory()->create())->post(route('price-uploads.activate', $upload));

    expect($upload->refresh()->status)->toBe(PriceUploadStatus::NeedsReview);
});

test('an unknown upload is not found', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('price-uploads.show', 999))
        ->assertNotFound();
});
