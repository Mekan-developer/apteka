<?php

namespace App\Http\Requests\Synonyms;

use App\Repositories\DrugSynonymRepository;
use App\Services\Prices\Parsing\NameNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDrugSynonymRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term' => ['required', 'string', 'max:100'],
            'synonym' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * The names are compared as the search sees them: «Аспирин таб.» and «аспирин» are one name.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(NameNormalizer $normalizer, DrugSynonymRepository $synonyms): array
    {
        return [
            function (Validator $validator) use ($normalizer, $synonyms): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $term = $normalizer->normalize($this->string('term')->toString())->baseName;
                $synonym = $normalizer->normalize($this->string('synonym')->toString())->baseName;

                if ($term === $synonym) {
                    $validator->errors()->add('synonym', __('messages.synonym_same'));
                } elseif ($synonyms->pairExists($term, $synonym)) {
                    $validator->errors()->add('synonym', __('messages.synonym_exists'));
                }
            },
        ];
    }
}
