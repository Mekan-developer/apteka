<?php

namespace App\Http\Requests\Prices;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchPricesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * A query shorter than the minimum matches nearly every product, so it is refused.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'min:'.config('prices.search.min_query_length'), 'max:100'],
        ];
    }

    /**
     * Spaces around the query do not count towards its length.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    /**
     * The searched drug name without surrounding spaces; empty when nothing was searched yet.
     */
    public function searchQuery(): string
    {
        return trim((string) $this->validated('q', ''));
    }
}
