<?php

namespace App\Http\Controllers;

use App\Http\Requests\Prices\SearchPricesRequest;
use App\Http\Resources\PriceSearchResultResource;
use App\Services\Prices\PriceSearchResult;
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
        $result = $query === '' ? PriceSearchResult::empty() : $search->search($query);

        return Inertia::render('Search', [
            'query' => $query,
            'minQueryLength' => (int) config('prices.search.min_query_length'),
            'pricesDate' => $search->activeUpload()?->activated_at?->toIso8601String(),
            'pricesStale' => $search->pricesAreStale(),
            'pharmaciesCount' => $search->pharmaciesCount(),
            'popularQueries' => config('prices.search.popular'),
            'result' => (new PriceSearchResultResource($result))->resolve(),
            'suggestions' => $query !== '' && $result->groups === [] ? $search->suggestions($query) : [],
        ]);
    }
}
