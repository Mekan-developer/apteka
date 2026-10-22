<?php

namespace App\Http\Controllers;

use App\Http\Requests\Prices\StorePriceUploadRequest;
use App\Http\Resources\PriceUploadResource;
use App\Models\PriceUpload;
use App\Services\Prices\PriceUploadService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PriceUploadController extends Controller
{
    public function __construct(private readonly PriceUploadService $uploads) {}

    /**
     * Show the upload form and the history of uploads.
     */
    public function index(): Response
    {
        return Inertia::render('price-uploads/Index', [
            'uploads' => PriceUploadResource::collection($this->uploads->paginateForAdmin()),
            'maxUploadMegabytes' => intdiv(config('prices.max_upload_kilobytes'), 1024),
        ]);
    }

    /**
     * Store a weekly price file and queue its import.
     */
    public function store(StorePriceUploadRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $upload = $this->uploads->upload($file, $request->user());

        notify('success', __('messages.price_upload_started', ['name' => $file->getClientOriginalName()]));

        return to_route('price-uploads.show', $upload);
    }

    /**
     * Show the import progress and the result of every sheet.
     */
    public function show(PriceUpload $priceUpload): Response
    {
        return Inertia::render('price-uploads/Show', [
            // resolve() hands the page the upload itself instead of wrapping it in "data".
            'upload' => (new PriceUploadResource($this->uploads->withSheets($priceUpload)))->resolve(),
        ]);
    }
}
