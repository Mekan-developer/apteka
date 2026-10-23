<?php

namespace App\Http\Controllers;

use App\Http\Requests\Prices\SearchPricesRequest;
use App\Http\Resources\PriceHistoryRowResource;
use App\Http\Resources\PriceUploadResource;
use App\Services\Prices\PriceSearchService;
use Inertia\Inertia;
use Inertia\Response;

class PriceHistoryController extends Controller
{
    /**
     * How the cheapest price of a drug changed over the last published uploads.
     */
    public function __invoke(SearchPricesRequest $request, PriceSearchService $search): Response
    {
        $query = $request->searchQuery();
        $history = $query === '' ? null : $search->history($query);

        return Inertia::render('price-history/Index', [
            'query' => $query,
            'uploads' => $history === null ? [] : PriceUploadResource::collection($history->uploads)->resolve(),
            'rows' => $history === null ? [] : PriceHistoryRowResource::collection($history->rows)->resolve(),
        ]);
    }
}
