<?php

namespace App\Jobs;

use App\Models\PriceUpload;
use App\Services\Prices\PriceImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Parses an uploaded price file. Running it again replaces what an earlier attempt saved.
 * Keep the queue's retry_after above $timeout, or a long import is started twice.
 */
class ImportPriceUploadJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * An upload deleted before its import ran needs no import.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public PriceUpload $priceUpload) {}

    /**
     * Seconds to wait before each retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    /**
     * Execute the job.
     */
    public function handle(PriceImportService $imports): void
    {
        $imports->import($this->priceUpload);
    }

    /**
     * Show the admin that the file could not be imported once all attempts have failed.
     */
    public function failed(?Throwable $exception): void
    {
        app(PriceImportService::class)->markFailed($this->priceUpload);
    }
}
