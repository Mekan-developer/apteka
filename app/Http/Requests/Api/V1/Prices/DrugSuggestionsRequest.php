<?php

namespace App\Http\Requests\Api\V1\Prices;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DrugSuggestionsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:'.config('prices.search.min_query_length'), 'max:100'],
        ];
    }

    /**
     * Spaces around the typed text do not count towards its length.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    /**
     * What the visitor has typed so far.
     */
    public function typedText(): string
    {
        return (string) $this->validated('q');
    }
}
