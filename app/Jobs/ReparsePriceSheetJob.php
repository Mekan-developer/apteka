<?php

namespace App\Jobs;

use App\Models\PriceUploadSheet;
use App\Services\Prices\Parsing\ColumnMapping;
use App\Services\Prices\PriceImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Parses one sheet again with the columns an admin chose. Running it again gives the same result.
 * Row numbers are as in Excel (1-based); column indexes are zero-based (A = 0).
 */
class ReparsePriceSheetJob implements ShouldQueue
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
     * A sheet deleted before the job ran needs no parsing.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PriceUploadSheet $sheet,
        public int $headerRow,
        public int $nameCol,
        public int $priceCol,
        public ?int $priceDiscountedCol = null,
    ) {}

    /**
     * Seconds to wait before each retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    /**
     * Execute the job.
     */
    public function handle(PriceImportService $imports): void
    {
        $imports->reparseSheet($this->sheet, new ColumnMapping(
            headerRow: $this->headerRow,
            lastHeaderRow: $this->headerRow,
            nameCol: $this->nameCol,
            priceCol: $this->priceCol,
            priceDiscountedCol: $this->priceDiscountedCol,
        ));
    }

    /**
     * Show the admin that the sheet could not be parsed once all attempts have failed.
     */
    public function failed(?Throwable $exception): void
    {
        app(PriceImportService::class)->markSheetFailed($this->sheet, (string) $exception?->getMessage());
    }
}
