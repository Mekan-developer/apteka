<?php

namespace App\Http\Controllers;

use App\Http\Requests\Prices\SearchPricesRequest;
use App\Http\Resources\PriceSearchGroupResource;
use App\Services\Prices\PriceSearchService;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    /**
     * The public search: the cheapest pharmacies for a drug, without signing in.
     */
    public function __invoke(SearchPricesRequest $request, PriceSearchService $search): Response
    {
        $query = $request->searchQuery();
        $groups = $query === '' ? [] : $search->search($query);

        return Inertia::render('Search', [
            'query' => $query,
            'pricesDate' => $search->activeUpload()?->activated_at?->toIso8601String(),
            'groups' => PriceSearchGroupResource::collection($groups)->resolve(),
            'suggestions' => $query !== '' && $groups === [] ? $search->suggestions($query) : [],
        ]);
    }
}
