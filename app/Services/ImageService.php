<?php

namespace App\Services;

use App\Jobs\ConvertImageToWebpJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use RuntimeException;

class ImageService
{
    /**
     * Store the upload temporarily and queue its conversion to WebP.
     *
     * Returns the path the WebP file will have on the images disk once the job has run.
     */
    public function queueWebpConversion(UploadedFile $file, string $directory): string
    {
        $name = Str::uuid()->toString();

        $temporaryPath = $file->storeAs(
            config('images.temporary_directory'),
            "{$name}.{$file->extension()}",
            config('images.temporary_disk'),
        );

        if ($temporaryPath === false) {
            throw new RuntimeException("Unable to store the temporary image [{$file->getClientOriginalName()}].");
        }

        $targetPath = trim($directory, '/')."/{$name}.webp";

        ConvertImageToWebpJob::dispatch($temporaryPath, $targetPath);

        return $targetPath;
    }

    /**
     * Convert the temporary original to WebP, store it and delete the original.
     *
     * Safe to run more than once: when the original is already gone there is nothing left to do.
     */
    public function convertToWebp(string $temporaryPath, string $targetPath): void
    {
        $temporaryDisk = Storage::disk(config('images.temporary_disk'));

        if (! $temporaryDisk->exists($temporaryPath)) {
            return;
        }

        $webp = ImageManager::gd()
            ->read($temporaryDisk->get($temporaryPath))
            ->toWebp(config('images.webp_quality'));

        Storage::disk(config('images.disk'))->put($targetPath, (string) $webp);

        $temporaryDisk->delete($temporaryPath);
    }

    /**
     * Remove a temporary original that could not be converted.
     */
    public function discardTemporary(string $temporaryPath): void
    {
        Storage::disk(config('images.temporary_disk'))->delete($temporaryPath);
    }
}
