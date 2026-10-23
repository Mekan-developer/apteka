<?php

namespace App\Http\Requests\Prices;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchPricesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * The searched drug name without surrounding spaces; empty when nothing was searched yet.
     */
    public function searchQuery(): string
    {
        return trim((string) $this->validated('q', ''));
    }
}
