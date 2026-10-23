<?php

namespace App\Actions\Synonyms;

use App\Models\DrugSynonym;
use App\Repositories\DrugSynonymRepository;

class DeleteDrugSynonymAction
{
    public function __construct(private readonly DrugSynonymRepository $synonyms) {}

    public function handle(DrugSynonym $synonym): void
    {
        $this->synonyms->delete($synonym);
    }
}
