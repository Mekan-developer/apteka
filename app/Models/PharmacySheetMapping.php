<?php

namespace App\Models;

use Database\Factories\PharmacySheetMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Column layout an admin confirmed for a pharmacy's sheet, reused on the next upload.
 * It is applied automatically only while the sheet header still matches header_signature.
 * Row numbers are as in Excel (1-based); column indexes are zero-based (A = 0).
 *
 * @property int $id
 * @property int $pharmacy_id
 * @property int $header_row
 * @property int $name_col
 * @property int $price_col
 * @property int|null $price_discounted_col
 * @property string $header_signature
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'pharmacy_id',
    'header_row',
    'name_col',
    'price_col',
    'price_discounted_col',
    'header_signature',
])]
class PharmacySheetMapping extends Model
{
    /** @use HasFactory<PharmacySheetMappingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Pharmacy, $this>
     */
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
