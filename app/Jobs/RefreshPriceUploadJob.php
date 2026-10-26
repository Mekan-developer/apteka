<?php

namespace App\Jobs;

use App\Models\PriceUpload;
use App\Services\Prices\PriceImportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Brings an upload imported by an earlier version up to date: the name fields search uses and the pharmacy
 * contacts. Running it again gives the same result.
 *
 * Keep the queue's retry_after above $timeout, or a long refresh is started twice.
 */
class RefreshPriceUploadJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out: reading every sheet of a ~20 MB file.
     */
    public int $timeout = 300;

    /**
     * An upload deleted before the job ran needs no refresh.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public PriceUpload $priceUpload) {}

    /**
     * One refresh of an upload at a time.
     */
    public function uniqueId(): string
    {
        return (string) $this->priceUpload->id;
    }

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
        $imports->refresh($this->priceUpload);
    }
}
