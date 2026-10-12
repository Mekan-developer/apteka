<?php

namespace App\Jobs;

use App\Services\ImageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ConvertImageToWebpJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $temporaryPath,
        public string $targetPath,
    ) {}

    /**
     * Seconds to wait before each retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Execute the job.
     */
    public function handle(ImageService $imageService): void
    {
        $imageService->convertToWebp($this->temporaryPath, $this->targetPath);
    }

    /**
     * Clean up the temporary original once all attempts have failed.
     */
    public function failed(?Throwable $exception): void
    {
        app(ImageService::class)->discardTemporary($this->temporaryPath);
    }
}
