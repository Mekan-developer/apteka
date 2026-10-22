<?php

namespace App\Http\Resources;

use App\Enums\PriceUploadSheetStatus;
use App\Models\PriceUploadSheet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceUploadSheet
 */
class PriceUploadSheetResource extends JsonResource
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
            'sheet_name' => $this->sheet_name,
            'sheet_index' => $this->sheet_index,
            'status' => $this->status->value,
            'rows_parsed' => $this->rows_parsed,
            'review_reason' => $this->status === PriceUploadSheetStatus::NeedsReview ? $this->error : null,
        ];
    }
}
