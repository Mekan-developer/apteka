<?php

namespace App\Repositories;

use App\Models\DrugSynonym;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class DrugSynonymRepository
{
    /**
     * @return Collection<int, DrugSynonym>
     */
    public function allForAdmin(): Collection
    {
        return DrugSynonym::query()->orderBy('term_normalized')->orderBy('synonym_normalized')->get();
    }

    /**
     * @param  array{term: string, synonym: string, term_normalized: string, synonym_normalized: string}  $attributes
     */
    public function create(array $attributes): DrugSynonym
    {
        return DrugSynonym::query()->create($attributes);
    }

    public function delete(DrugSynonym $synonym): void
    {
        $synonym->delete();
    }

    /**
     * A pair is the same in either order: «аспирин = АСК» already covers «АСК = аспирин».
     */
    public function pairExists(string $termNormalized, string $synonymNormalized): bool
    {
        return DrugSynonym::query()
            ->where(fn ($query) => $query
                ->where('term_normalized', $termNormalized)
                ->where('synonym_normalized', $synonymNormalized))
            ->orWhere(fn ($query) => $query
                ->where('term_normalized', $synonymNormalized)
                ->where('synonym_normalized', $termNormalized))
            ->exists();
    }

    /**
     * Every other name of the drug: the names linked to it and the names linked to those
     * («ACTION PLUS» = «цитрамон» = «цитрамон П» → searching «ACTION PLUS» also finds «цитрамон П»).
     *
     * @return list<string>
     */
    public function namesRelatedTo(string $normalizedName): array
    {
        $rows = DB::select(
            <<<'SQL'
                with direct as (
                    select term_normalized as name from drug_synonyms where synonym_normalized = ?
                    union
                    select synonym_normalized from drug_synonyms where term_normalized = ?
                    union
                    select ?
                )
                select term_normalized as name from drug_synonyms where synonym_normalized in (select name from direct)
                union
                select synonym_normalized from drug_synonyms where term_normalized in (select name from direct)
                union
                select name from direct
                SQL,
            [$normalizedName, $normalizedName, $normalizedName],
        );

        return array_values(array_filter(
            array_map(fn (stdClass $row): string => (string) $row->name, $rows),
            fn (string $name): bool => $name !== $normalizedName,
        ));
    }
}
