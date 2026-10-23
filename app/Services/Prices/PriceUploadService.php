<?php

namespace App\Services\Prices;

use App\Enums\PriceUploadStatus;
use App\Jobs\ImportPriceUploadJob;
use App\Models\PriceUpload;
use App\Models\User;
use App\Repositories\PriceUploadRepository;
use App\Repositories\PriceUploadSheetRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PriceUploadService
{
    public function __construct(
        private readonly PriceUploadRepository $uploads,
        private readonly PriceUploadSheetRepository $sheets,
    ) {}

    /**
     * Keep the file as history and queue its import.
     */
    public function upload(UploadedFile $file, User $user): PriceUpload
    {
        $storedPath = $file->storeAs(config('prices.directory'), Str::uuid().'.xlsx', config('prices.disk'));

        if ($storedPath === false) {
            throw new RuntimeException("Unable to store the price file [{$file->getClientOriginalName()}].");
        }

        $upload = $this->uploads->create([
            'user_id' => $user->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => PriceUploadStatus::Uploaded,
        ]);

        ImportPriceUploadJob::dispatch($upload);

        return $upload;
    }

    /**
     * @return LengthAwarePaginator<int, PriceUpload>
     */
    public function paginateForAdmin(): LengthAwarePaginator
    {
        return $this->uploads->paginateForAdmin();
    }

    public function withSheets(PriceUpload $upload): PriceUpload
    {
        return $this->uploads->loadWithSheets($upload);
    }

    /**
     * Make the upload the one search uses; the previously active one becomes history.
     * An upload still being parsed, or one that failed, cannot be activated.
     */
    public function activate(PriceUpload $upload): bool
    {
        if (! in_array($upload->status, [PriceUploadStatus::NeedsReview, PriceUploadStatus::Archived], true)
            || $this->sheets->hasProcessing($upload)) {
            return false;
        }

        DB::transaction(function () use ($upload): void {
            $this->uploads->archiveActive();
            $this->uploads->activate($upload);
        });

        return true;
    }
}
