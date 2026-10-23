<?php

namespace App\Http\Resources;

use App\Models\PriceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One pharmacy's cheapest offer for the searched drug in a pack size.
 *
 * @mixin PriceItem
 */
class SearchOfferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'place' => (int) $this->resource->getAttribute('place'),
            'pharmacy' => $this->whenLoaded('pharmacy', fn (): array => [
                'name' => $this->pharmacy->name,
                'phone' => $this->pharmacy->phone,
            ]),
            'raw_name' => $this->raw_name,
            'dose' => $this->dose,
            'manufacturer' => $this->manufacturer,
            'price' => $this->price,
            'price_discounted' => $this->price_discounted,
            'effective_price' => $this->effective_price,
            'goods_discount_percent' => $this->goods_discount_percent,
            'discount_note' => $this->discount_note,
            'sheet_discounts' => $this->whenLoaded(
                'sheetDiscounts',
                fn (): array => DiscountRuleResource::collection($this->resource->getRelation('sheetDiscounts'))->resolve($request),
            ),
        ];
    }
}
