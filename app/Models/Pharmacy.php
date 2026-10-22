<?php

namespace App\Models;

use Database\Factories\PharmacyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A supplier pharmacy; its name is the sheet name in the uploaded price file.
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'phone'])]
class Pharmacy extends Model
{
    /** @use HasFactory<PharmacyFactory> */
    use HasFactory;

    /**
     * @return HasOne<PharmacySheetMapping, $this>
     */
    public function sheetMapping(): HasOne
    {
        return $this->hasOne(PharmacySheetMapping::class);
    }

    /**
     * @return HasMany<PriceUploadSheet, $this>
     */
    public function uploadSheets(): HasMany
    {
        return $this->hasMany(PriceUploadSheet::class);
    }

    /**
     * @return HasMany<PriceItem, $this>
     */
    public function priceItems(): HasMany
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
