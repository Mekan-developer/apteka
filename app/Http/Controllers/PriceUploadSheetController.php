<?php

namespace App\Http\Controllers;

use App\Http\Requests\Prices\ReparsePriceSheetRequest;
use App\Http\Resources\DiscountRuleResource;
use App\Http\Resources\PriceItemResource;
use App\Http\Resources\PriceUploadResource;
use App\Http\Resources\PriceUploadSheetDetailResource;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use App\Services\Prices\PriceSheetReviewService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PriceUploadSheetController extends Controller
{
    public function __construct(private readonly PriceSheetReviewService $review) {}

    /**
     * Show the raw rows of a sheet, the chosen columns and what was parsed with them.
     */
    public function show(PriceUpload $priceUpload, PriceUploadSheet $sheet): Response
    {
        return Inertia::render('price-uploads/sheets/Show', [
            'upload' => (new PriceUploadResource($priceUpload))->resolve(),
            'sheet' => (new PriceUploadSheetDetailResource($sheet))->resolve(),
            'items' => PriceItemResource::collection($this->review->sampleItems($sheet))->resolve(),
            'discounts' => DiscountRuleResource::collection($this->review->discounts($sheet))->resolve(),
        ]);
    }

    /**
     * Parse the sheet again with the columns the admin chose.
     */
    public function update(ReparsePriceSheetRequest $request, PriceUpload $priceUpload, PriceUploadSheet $sheet): RedirectResponse
    {
        if ($this->review->requestReparse($priceUpload, $sheet, $request->columns())) {
            notify('info', __('messages.price_sheet_reparse_started', ['name' => $sheet->sheet_name]));
        } else {
            notify('warning', __('messages.price_sheet_reparse_busy'));
        }

        return to_route('price-uploads.sheets.show', [$priceUpload, $sheet]);
    }
}
