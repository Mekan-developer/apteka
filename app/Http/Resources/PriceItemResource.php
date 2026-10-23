<?php

namespace App\Http\Resources;

use App\Models\PriceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceItem
 */
class PriceItemResource extends JsonResource
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
            'source_row' => $this->source_row,
            'raw_name' => $this->raw_name,
            'base_name' => $this->base_name,
            'pack_count' => $this->pack_count,
            'dose' => $this->dose,
            'price' => $this->price,
            'price_discounted' => $this->price_discounted,
            'effective_price' => $this->effective_price,
            'goods_discount_percent' => $this->goods_discount_percent,
            'discount_note' => $this->discount_note,
            'manufacturer' => $this->manufacturer,
        ];
    }
}
