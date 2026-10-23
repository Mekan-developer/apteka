<?php

namespace App\Http\Requests\Prices;

use App\Models\PriceUploadSheet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Columns an admin chose for a sheet. Row numbers are as in Excel; column indexes are zero-based (A = 0).
 */
class ReparsePriceSheetRequest extends FormRequest
{
    private const int MAX_COLUMN = 255;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PriceUploadSheet $sheet */
        $sheet = $this->route('sheet');
        $previewRows = array_column($sheet->preview_json ?? [], 'row');

        return [
            'header_row' => ['required', 'integer', Rule::in($previewRows)],
            'name_col' => ['required', 'integer', 'min:0', 'max:'.self::MAX_COLUMN],
            'price_col' => ['required', 'integer', 'min:0', 'max:'.self::MAX_COLUMN, 'different:name_col'],
            'price_discounted_col' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_COLUMN, 'different:name_col', 'different:price_col'],
        ];
    }

    /**
     * @return array{header_row: int, name_col: int, price_col: int, price_discounted_col: int|null}
     */
    public function columns(): array
    {
        return [
            'header_row' => $this->integer('header_row'),
            'name_col' => $this->integer('name_col'),
            'price_col' => $this->integer('price_col'),
            'price_discounted_col' => $this->filled('price_discounted_col') ? $this->integer('price_discounted_col') : null,
        ];
    }
}
