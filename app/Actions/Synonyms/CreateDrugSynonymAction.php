<?php

namespace App\Actions\Synonyms;

use App\Models\DrugSynonym;
use App\Repositories\DrugSynonymRepository;
use App\Services\Prices\Parsing\NameNormalizer;

class CreateDrugSynonymAction
{
    public function __construct(
        private readonly DrugSynonymRepository $synonyms,
        private readonly NameNormalizer $normalizer,
    ) {}

    /**
     * Keep both names as typed and as the base names the search compares with product names.
     */
    public function handle(string $term, string $synonym): DrugSynonym
    {
        return $this->synonyms->create([
            'term' => trim($term),
            'synonym' => trim($synonym),
            'term_normalized' => $this->normalizer->normalize($term)->baseName,
            'synonym_normalized' => $this->normalizer->normalize($synonym)->baseName,
        ]);
    }
}
