<?php

namespace App\Http\Resources;

use App\Services\Prices\PriceHistoryRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceHistoryRow
 */
class PriceHistoryRowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // A list with the upload id inside: resources renumber arrays with numeric keys.
        $prices = [];

        foreach ($this->offersByUpload as $uploadId => $offer) {
            $prices[] = ['upload_id' => $uploadId, 'offer' => (new SearchOfferResource($offer))->resolve($request)];
        }

        return [
            'pack_count' => $this->packCount,
            'prices' => $prices,
        ];
    }
}
