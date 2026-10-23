<?php

namespace App\Http\Resources;

use App\Models\DiscountRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiscountRule
 */
class DiscountRuleResource extends JsonResource
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
            'type' => $this->type->value,
            'percent' => $this->percent,
            'min_sum' => $this->min_sum,
            'is_conditional' => $this->is_conditional,
            'raw_text' => $this->raw_text,
        ];
    }
}
