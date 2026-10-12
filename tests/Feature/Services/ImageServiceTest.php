<?php

use App\Jobs\ConvertImageToWebpJob;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('an upload is stored temporarily and its webp conversion is queued', function () {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake([ConvertImageToWebpJob::class]);

    $targetPath = app(ImageService::class)
        ->queueWebpConversion(UploadedFile::fake()->image('photo.jpg'), 'avatars/');

    expect($targetPath)->toMatch('#^avatars/[0-9a-f-]{36}\.webp$#');

    Queue::assertPushed(ConvertImageToWebpJob::class, function (ConvertImageToWebpJob $job) use ($targetPath): bool {
        Storage::disk('local')->assertExists($job->temporaryPath);

        return $job->targetPath === $targetPath
            && str_starts_with($job->temporaryPath, 'tmp/images/');
    });

    Storage::disk('public')->assertMissing($targetPath);
});

test('the queued upload ends up stored only as webp', function () {
    Storage::fake('local');
    Storage::fake('public');

    $targetPath = app(ImageService::class)
        ->queueWebpConversion(UploadedFile::fake()->image('photo.png', 40, 30), 'avatars');

    Storage::disk('public')->assertExists($targetPath);
    expect(Storage::disk('public')->mimeType($targetPath))->toBe('image/webp');
    expect(Storage::disk('public')->allFiles())->toBe([$targetPath]);
    expect(Storage::disk('local')->allFiles('tmp/images'))->toBe([]);
});

test('converting an already converted image again changes nothing', function () {
    Storage::fake('local');
    Storage::fake('public');
    Storage::disk('local')->put('tmp/images/photo.jpg', UploadedFile::fake()->image('photo.jpg')->getContent());
    $imageService = app(ImageService::class);

    $imageService->convertToWebp('tmp/images/photo.jpg', 'avatars/photo.webp');
    $firstWebp = Storage::disk('public')->get('avatars/photo.webp');

    $imageService->convertToWebp('tmp/images/photo.jpg', 'avatars/photo.webp');

    expect(Storage::disk('public')->get('avatars/photo.webp'))->toBe($firstWebp);
});
