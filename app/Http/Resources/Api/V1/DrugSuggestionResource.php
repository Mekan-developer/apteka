<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A drug name the search box suggests, with the number of pharmacies that sell it.
 *
 * @property array{name: string, pharmacies_count: int} $resource
 */
class DrugSuggestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => Str::ucfirst($this->resource['name']),
            'pharmacies_count' => $this->resource['pharmacies_count'],
        ];
    }
}
