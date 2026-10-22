<?php

namespace App\Models;

use App\Enums\DiscountRuleType;
use Database\Factories\DiscountRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A discount a pharmacy states for its whole sheet, e.g. "Товарная скидка 5%"
 * or "от 10 000 ман −5%" (min_sum is set for discounts that depend on the order sum).
 * A conditional discount (order sum, payment in cash, a number of boxes) never lowers the search price.
 *
 * @property int $id
 * @property int $price_upload_id
 * @property int $pharmacy_id
 * @property DiscountRuleType $type
 * @property string $percent
 * @property string|null $min_sum
 * @property bool $is_conditional
 * @property string $raw_text
 */
#[Fillable(['price_upload_id', 'pharmacy_id', 'type', 'percent', 'min_sum', 'is_conditional', 'raw_text'])]
#[WithoutTimestamps]
class DiscountRule extends Model
{
    /** @use HasFactory<DiscountRuleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountRuleType::class,
            'percent' => 'decimal:2',
            'min_sum' => 'decimal:2',
            'is_conditional' => 'boolean',
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
