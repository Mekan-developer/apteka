<?php

namespace App\Actions\Synonyms;

use App\Models\DrugSynonym;
use App\Repositories\DrugSynonymRepository;
use Illuminate\Database\Eloquent\Collection;

class ListDrugSynonymsAction
{
    public function __construct(private readonly DrugSynonymRepository $synonyms) {}

    /**
     * @return Collection<int, DrugSynonym>
     */
    public function handle(): Collection
    {
        return $this->synonyms->allForAdmin();
    }
}
