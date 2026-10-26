<?php

namespace App\Console\Commands;

use App\Jobs\RefreshPriceUploadJob;
use App\Repositories\PriceUploadRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('prices:refresh')]
#[Description('Queue an update of every stored upload to what search needs now: name fields such as the dosage form and the pharmacy contacts')]
class RefreshPricesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PriceUploadRepository $uploads): int
    {
        $refreshable = $uploads->withProducts();

        foreach ($refreshable as $upload) {
            RefreshPriceUploadJob::dispatch($upload);
        }

        $this->info(__('prices.refresh.queued', ['count' => $refreshable->count()]));

        return self::SUCCESS;
    }
}
