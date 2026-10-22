<?php

namespace App\Http\Resources;

use App\Models\PriceUpload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceUpload
 */
class PriceUploadResource extends JsonResource
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
            'original_filename' => $this->original_filename,
            'status' => $this->status->value,
            'sheets_total' => $this->sheets_total,
            'sheets_processed' => $this->whenCounted('sheets'),
            'sheets_ok' => $this->sheets_ok,
            'rows_total' => $this->rows_total,
            'uploaded_by' => $this->whenLoaded('user', fn (): ?string => $this->user?->name),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'sheets' => $this->whenLoaded(
                'sheets',
                fn (): array => PriceUploadSheetResource::collection($this->sheets)->resolve($request),
            ),
        ];
    }
}
