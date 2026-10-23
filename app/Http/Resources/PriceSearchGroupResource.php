<?php

namespace App\Http\Resources;

use App\Services\Prices\PriceSearchGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceSearchGroup
 */
class PriceSearchGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pack_count' => $this->packCount,
            'offers' => SearchOfferResource::collection($this->offers)->resolve($request),
        ];
    }
}
