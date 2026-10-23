<?php

namespace App\Models;

use Database\Factories\PriceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product row of a pharmacy's price sheet.
 *
 * effective_price is what search sorts by: the price after an unconditional price discount
 * on the whole sheet. Goods discounts and discounts from an order sum never change it.
 *
 * @property int $id
 * @property int $price_upload_id
 * @property int $pharmacy_id
 * @property string $raw_name
 * @property string $name_normalized
 * @property string $name_skeleton
 * @property string $base_name
 * @property string $name_compact Base name without spaces, so «ношпа» finds «Но-шпа».
 * @property string|null $active_ingredient
 * @property int|null $pack_count
 * @property string|null $dose
 * @property string $price
 * @property string|null $price_discounted
 * @property string $effective_price
 * @property string|null $goods_discount_percent
 * @property string|null $discount_note
 * @property string|null $manufacturer
 * @property int $source_row
 */
#[Fillable([
    'price_upload_id',
    'pharmacy_id',
    'raw_name',
    'name_normalized',
    'name_skeleton',
    'base_name',
    'name_compact',
    'active_ingredient',
    'pack_count',
    'dose',
    'price',
    'price_discounted',
    'effective_price',
    'goods_discount_percent',
    'discount_note',
    'manufacturer',
    'source_row',
])]
#[WithoutTimestamps]
class PriceItem extends Model
{
    /** @use HasFactory<PriceItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_discounted' => 'decimal:2',
            'effective_price' => 'decimal:2',
            'goods_discount_percent' => 'decimal:2',
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
