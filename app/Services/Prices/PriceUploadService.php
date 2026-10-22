<?php

namespace App\Services\Prices;

use App\Enums\PriceUploadStatus;
use App\Jobs\ImportPriceUploadJob;
use App\Models\PriceUpload;
use App\Models\User;
use App\Repositories\PriceUploadRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class PriceUploadService
{
    public function __construct(private readonly PriceUploadRepository $uploads) {}

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
}
