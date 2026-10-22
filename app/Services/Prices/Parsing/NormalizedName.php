<?php

namespace App\Services\Prices\Parsing;

/**
 * A product name prepared for search: the same normalization runs on import and on the search query.
 */
final readonly class NormalizedName
{
    /**
     * @param  string  $normalized  Lowercase name without punctuation.
     * @param  string  $skeleton  Consonants of the base name with repeats collapsed: «аналгин» and «анолгин» → «нлгн».
     * @param  string  $baseName  Name without pack, dose, dosage form and words in parentheses.
     * @param  string|null  $activeIngredient  Words from the first parentheses, e.g. «парацетамол».
     * @param  int|null  $packCount  N from «№N»: prices are only comparable within one pack size.
     * @param  string|null  $dose  Every dose in the name, e.g. «20% 50мл».
     */
    public function __construct(
        public string $normalized,
        public string $skeleton,
        public string $baseName,
        public ?string $activeIngredient,
        public ?int $packCount,
        public ?string $dose,
    ) {}
}
