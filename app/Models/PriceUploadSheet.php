<?php

namespace App\Models;

use App\Enums\PriceUploadSheetStatus;
use Database\Factories\PriceUploadSheetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How one sheet (one pharmacy) of an upload was parsed.
 * Row numbers are as in Excel (1-based); column indexes are zero-based (A = 0).
 *
 * @property int $id
 * @property int $price_upload_id
 * @property int $pharmacy_id
 * @property string $sheet_name
 * @property int $sheet_index
 * @property PriceUploadSheetStatus $status
 * @property int|null $header_row
 * @property int|null $name_col
 * @property int|null $price_col
 * @property int|null $price_discounted_col
 * @property int $rows_parsed
 * @property string|null $error
 * @property list<list<mixed>>|null $preview_json First raw rows of the sheet for the review screen.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'price_upload_id',
    'pharmacy_id',
    'sheet_name',
    'sheet_index',
    'status',
    'header_row',
    'name_col',
    'price_col',
    'price_discounted_col',
    'rows_parsed',
    'error',
    'preview_json',
])]
class PriceUploadSheet extends Model
{
    /** @use HasFactory<PriceUploadSheetFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PriceUploadSheetStatus::class,
            'preview_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PriceUpload, $this>
     */
    public function priceUpload(): BelongsTo
    {
        return $this->belongsTo(PriceUpload::class);
    }

    /**
     * @return BelongsTo<Pharmacy, $this>
     */
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
