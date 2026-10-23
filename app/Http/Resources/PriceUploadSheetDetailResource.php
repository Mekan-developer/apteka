<?php

namespace App\Http\Resources;

use App\Models\PriceUploadSheet;
use Illuminate\Http\Request;

/**
 * A sheet with what the review screen needs: the chosen columns and the first raw rows.
 *
 * @mixin PriceUploadSheet
 */
class PriceUploadSheetDetailResource extends PriceUploadSheetResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'header_row' => $this->header_row,
            'name_col' => $this->name_col,
            'price_col' => $this->price_col,
            'price_discounted_col' => $this->price_discounted_col,
            'preview' => $this->preview_json ?? [],
        ];
    }
}
