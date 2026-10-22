<?php

namespace App\Models;

use App\Enums\PriceUploadStatus;
use Database\Factories\PriceUploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One weekly Excel price file. Only the single Active upload takes part in search;
 * the database refuses a second active upload.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $original_filename
 * @property string $stored_path
 * @property PriceUploadStatus $status
 * @property int $sheets_total
 * @property int $sheets_ok
 * @property int $rows_total
 * @property Carbon|null $activated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'original_filename',
    'stored_path',
    'status',
    'sheets_total',
    'sheets_ok',
    'rows_total',
    'activated_at',
])]
class PriceUpload extends Model
{
    /** @use HasFactory<PriceUploadFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PriceUploadStatus::class,
            'activated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PriceUploadSheet, $this>
     */
    public function sheets(): HasMany
    {
        return $this->hasMany(PriceUploadSheet::class);
    }

    /**
     * @return HasMany<PriceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PriceItem::class);
    }

    /**
     * @return HasMany<DiscountRule, $this>
     */
    public function discountRules(): HasMany
    {
        return $this->hasMany(DiscountRule::class);
    }
}
