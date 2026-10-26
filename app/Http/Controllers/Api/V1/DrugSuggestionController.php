<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Prices\DrugSuggestionsRequest;
use App\Http\Resources\Api\V1\DrugSuggestionResource;
use App\Services\Prices\PriceSearchService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DrugSuggestionController extends Controller
{
    /**
     * Drug names from the active prices for the search box, as the visitor types.
     */
    public function __invoke(DrugSuggestionsRequest $request, PriceSearchService $search): AnonymousResourceCollection
    {
        $names = $search->autocomplete($request->typedText());

        return DrugSuggestionResource::collection($names)->additional([
            'message' => $names === []
                ? __('messages.drug_suggestions_empty')
                : __('messages.drug_suggestions_found', ['count' => count($names)]),
        ]);
    }
}
