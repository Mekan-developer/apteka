<?php

namespace App\Http\Resources;

use App\Services\Prices\PriceSearchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin PriceSearchResult
 */
class PriceSearchResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'groups' => PriceSearchGroupResource::collection($this->groups)->resolve($request),
            'summary' => $this->groups === [] ? null : [
                'variants_count' => count($this->groups),
                'pharmacies_count' => $this->pharmaciesCount,
                'lowest_price' => $this->lowestPrice,
            ],
            'corrected_to' => $this->correctedTo === null
                ? null
                : Str::ucfirst($this->correctedTo),
        ];
    }
}
