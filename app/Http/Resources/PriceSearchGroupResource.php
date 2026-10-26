<?php

namespace App\Http\Resources;

use App\Services\Prices\PriceSearchGroup;
use App\Support\DoseLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

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
            'key' => $this->key(),
            'drug_name' => Str::ucfirst($this->drugName),
            'dosage_form' => $this->dosageForm?->value,
            'dosage_form_label' => $this->dosageForm?->label(),
            'dose' => $this->dose,
            'dose_label' => DoseLabel::format($this->dose),
            'pack_count' => $this->packCount,
            'pharmacies_count' => $this->pharmaciesCount,
            'offers' => SearchOfferResource::collection($this->offers)->resolve($request),
        ];
    }
}
