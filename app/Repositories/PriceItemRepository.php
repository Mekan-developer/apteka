<?php

namespace App\Repositories;

use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PriceItemRepository
{
    private const int INSERT_CHUNK = 500;

    /**
     * Bulk insert: a sheet has up to ~2 000 products and one upload ~20 000.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function insertMany(array $rows): void
    {
        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            PriceItem::query()->insert($chunk);
        }
    }

    /**
     * The upload's products in chunks, with just what normalization needs.
     *
     * @param  Closure(Collection<int, PriceItem>): void  $callback
     */
    public function chunkNamesForUpload(PriceUpload $upload, int $size, Closure $callback): void
    {
        PriceItem::query()->whereBelongsTo($upload)->select(['id', 'raw_name'])->chunkById($size, $callback);
    }

    /**
     * Rewrite the fields search derives from the product name, one statement per chunk.
     *
     * @param  list<array{id: int, name_normalized: string, name_skeleton: string, base_name: string, name_compact: string, active_ingredient: string|null, pack_count: int|null, dose: string|null, dosage_form: string|null}>  $rows
     */
    public function updateNames(array $rows): void
    {
        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            $values = implode(', ', array_fill(0, count($chunk), '(?::bigint, ?::text, ?::varchar, ?::text, ?::text, ?::text, ?::integer, ?::varchar, ?::varchar)'));
            $bindings = array_merge(...array_map(fn (array $row): array => [
                $row['id'], $row['name_normalized'], $row['name_skeleton'], $row['base_name'], $row['name_compact'],
                $row['active_ingredient'], $row['pack_count'], $row['dose'], $row['dosage_form'],
            ], $chunk));

            DB::update(<<<SQL
                update price_items as pi set
                    name_normalized = v.name_normalized, name_skeleton = v.name_skeleton, base_name = v.base_name,
                    name_compact = v.name_compact, active_ingredient = v.active_ingredient, pack_count = v.pack_count,
                    dose = v.dose, dosage_form = v.dosage_form
                from (values {$values}) as v(id, name_normalized, name_skeleton, base_name, name_compact, active_ingredient, pack_count, dose, dosage_form)
                where pi.id = v.id
                SQL, $bindings);
        }
    }

    public function deleteForUpload(PriceUpload $upload): void
    {
        PriceItem::query()->whereBelongsTo($upload)->delete();
    }

    /**
     * Refresh the planner statistics after an import. With stale statistics Postgres checks every product
     * of the upload (~120 ms per search) instead of using the trigram indexes (~5 ms).
     */
    public function refreshStatistics(): void
    {
        DB::statement('analyze price_items');
    }

    /**
     * A sheet's products are the upload's products of its pharmacy.
     */
    public function deleteForSheet(PriceUploadSheet $sheet): void
    {
        PriceItem::query()
            ->where('price_upload_id', $sheet->price_upload_id)
            ->where('pharmacy_id', $sheet->pharmacy_id)
            ->delete();
    }

    /**
     * The cheapest pharmacies for a searched drug, $perPack for every pack size, in one query:
     * relevant products (see relevantSql()) → each pharmacy's cheapest offer per pack size → the cheapest
     * pharmacies. Every returned item carries its "score" and its "place". Used by the price history.
     *
     * @param  non-empty-list<string>  $terms  The searched name first, then its synonyms; each counts as a full match.
     * @param  list<string>  $skeletons  The consonants of every spelling of the searched name.
     * @return Collection<int, PriceItem>
     */
    public function cheapestOffers(
        PriceUpload $upload,
        array $terms,
        array $skeletons,
        ?int $packCount,
        float $threshold,
        float $relativeGap,
        float $skeletonScore,
        int $perPack,
    ): Collection {
        [$relevantSql, $bindings] = $this->relevantSql($upload, $terms, $skeletons, $packCount, $threshold, $relativeGap, $skeletonScore);

        $sql = <<<SQL
            {$relevantSql},
            cheapest_per_pharmacy as (
                select distinct on (pack_count, pharmacy_id) *
                from relevant
                order by pack_count, pharmacy_id, effective_price, score desc
            ),
            ranked as (
                select *, row_number() over (partition by pack_count order by effective_price, score desc) as place
                from cheapest_per_pharmacy
            )
            select * from ranked
            where place <= ?
            order by pack_count nulls last, place
            SQL;

        return $this->withTrigramThreshold($threshold, fn (): Collection => PriceItem::fromQuery($sql, [...$bindings, $perPack])
            ->load('pharmacy'));
    }

    /**
     * Each pharmacy's cheapest offer of every variant (dosage form, dose and pack size) of a searched drug.
     * Variants are merged and ranked by the search service; every item carries its "score" and "best_score".
     *
     * @param  non-empty-list<string>  $terms  The searched name first, then its synonyms; each counts as a full match.
     * @param  list<string>  $skeletons  The consonants of every spelling of the searched name.
     * @return Collection<int, PriceItem>
     */
    public function cheapestOffersPerVariant(
        PriceUpload $upload,
        array $terms,
        array $skeletons,
        ?int $packCount,
        float $threshold,
        float $relativeGap,
        float $skeletonScore,
    ): Collection {
        [$relevantSql, $bindings] = $this->relevantSql($upload, $terms, $skeletons, $packCount, $threshold, $relativeGap, $skeletonScore);

        $sql = <<<SQL
            {$relevantSql}
            select distinct on (dosage_form, dose, pack_count, pharmacy_id) *
            from relevant
            order by dosage_form, dose, pack_count, pharmacy_id, effective_price, score desc
            SQL;

        return $this->withTrigramThreshold($threshold, fn (): Collection => PriceItem::fromQuery($sql, $bindings)
            ->load('pharmacy'));
    }

    /**
     * The products of the upload relevant to a searched drug, as a "relevant" CTE: candidates by whole-word
     * trigram similarity of the base name or active ingredient (or by consonants, for typos in vowels),
     * then only those close to the best match.
     *
     * Whole words matter: plain word similarity puts «силденафил цитрат» at 0.56 to «цитрамон».
     * The relative cutoff keeps a cheaper look-alike («Баралгин» for «аналгин») from outranking
     * the drug itself once the drug is found.
     *
     * @param  non-empty-list<string>  $terms
     * @param  list<string>  $skeletons
     * @return array{0: string, 1: list<mixed>}
     */
    private function relevantSql(
        PriceUpload $upload,
        array $terms,
        array $skeletons,
        ?int $packCount,
        float $threshold,
        float $relativeGap,
        float $skeletonScore,
    ): array {
        $scoreSql = [];
        $scoreBindings = [];
        $matchSql = [];
        $matchBindings = [];

        foreach ($terms as $term) {
            // «ношпа» and «Но-шпа» («но шпа» once normalized) meet when spaces are ignored.
            $compactTerm = str_replace(' ', '', $term);

            array_push(
                $scoreSql,
                'strict_word_similarity(?, pi.base_name)',
                'coalesce(strict_word_similarity(?, pi.active_ingredient), 0)',
                'strict_word_similarity(?, pi.name_compact)',
            );
            array_push($scoreBindings, $term, $term, $compactTerm);

            array_push($matchSql, '? <<% pi.base_name', '? <<% pi.active_ingredient', '? <<% pi.name_compact');
            array_push($matchBindings, $term, $term, $compactTerm);
        }

        foreach ($skeletons as $skeleton) {
            if (mb_strlen($skeleton) < 3) {
                continue;
            }

            // The skeleton holds only consonant letters, so it never contains LIKE wildcards.
            $scoreSql[] = 'case when pi.name_skeleton like ? then ?::real else 0 end';
            array_push($scoreBindings, $skeleton.'%', $skeletonScore);

            $matchSql[] = 'pi.name_skeleton like ?';
            $matchBindings[] = $skeleton.'%';
        }

        $filterBindings = [$upload->id];
        $packSql = '';

        if ($packCount !== null) {
            $packSql = 'and pi.pack_count = ?';
            $filterBindings[] = $packCount;
        }

        $scoreSql = implode(', ', $scoreSql);
        $matchSql = implode(' or ', $matchSql);

        $sql = <<<SQL
            with candidates as (
                select pi.*, greatest({$scoreSql}) as score
                from price_items pi
                where pi.price_upload_id = ?
                    {$packSql}
                    and ({$matchSql})
            ),
            scored as (
                select *, max(score) over () as best_score
                from candidates
            ),
            relevant as (
                select * from scored
                where score >= greatest(?::real, best_score - ?::real)
            )
            SQL;

        return [$sql, [...$scoreBindings, ...$filterBindings, ...$matchBindings, $threshold, $relativeGap]];
    }

    /**
     * "<<%" uses this threshold (pg_trgm's default is 0.5), which lets the GIN indexes serve the search.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $query
     * @return TResult
     */
    private function withTrigramThreshold(float $threshold, Closure $query): mixed
    {
        return DB::transaction(function () use ($threshold, $query): mixed {
            DB::select("select set_config('pg_trgm.strict_word_similarity_threshold', ?, true)", [(string) $threshold]);

            return $query();
        });
    }

    /**
     * Base names closest to the query, for «Возможно, вы имели в виду …».
     *
     * @return list<string>
     */
    public function closestBaseNames(PriceUpload $upload, string $term, float $minScore, int $limit): array
    {
        return array_values(PriceItem::query()
            ->whereBelongsTo($upload)
            ->where('base_name', '!=', '')
            ->select('base_name')
            ->selectRaw('max(greatest(similarity(?, base_name), strict_word_similarity(?, base_name))) as score', [$term, $term])
            ->groupBy('base_name')
            ->havingRaw('max(greatest(similarity(?, base_name), strict_word_similarity(?, base_name))) >= ?', [$term, $term, $minScore])
            ->orderByDesc('score')
            ->limit($limit)
            ->toBase()
            ->pluck('base_name')
            ->map(fn (mixed $name): string => (string) $name)
            ->all());
    }

    /**
     * Names for the search box: base names and active ingredients starting with one of the terms
     * («аз» → «азитромицин»), the ones sold by more pharmacies first.
     *
     * @param  non-empty-list<string>  $terms
     * @return list<array{name: string, pharmacies_count: int}>
     */
    public function namesStartingWith(PriceUpload $upload, array $terms, int $limit): array
    {
        $baseSql = [];
        $ingredientSql = [];
        $bindings = [];

        foreach ($terms as $term) {
            $prefix = $this->likePrefix($term);
            $baseSql[] = 'base_name like ? or name_compact like ?';
            $ingredientSql[] = 'active_ingredient like ?';
            array_push($bindings, $prefix, $this->likePrefix(str_replace(' ', '', $term)));
        }

        $ingredientBindings = array_map(fn (string $term): string => $this->likePrefix($term), $terms);
        $baseSql = implode(' or ', $baseSql);
        $ingredientSql = implode(' or ', $ingredientSql);

        $sql = <<<SQL
            select name, count(distinct pharmacy_id) as pharmacies_count
            from (
                select base_name as name, pharmacy_id from price_items
                where price_upload_id = ? and base_name <> '' and ({$baseSql})
                union all
                select active_ingredient as name, pharmacy_id from price_items
                where price_upload_id = ? and ({$ingredientSql})
            ) names
            group by name
            order by pharmacies_count desc, name
            limit ?
            SQL;

        return $this->nameRows(DB::select($sql, [$upload->id, ...$bindings, $upload->id, ...$ingredientBindings, $limit]));
    }

    /**
     * Names for the search box that only resemble the term (a typo in the first letters), most similar first.
     *
     * @return list<array{name: string, pharmacies_count: int}>
     */
    public function namesResembling(PriceUpload $upload, string $term, float $threshold, int $limit): array
    {
        $sql = <<<'SQL'
            select base_name as name, count(distinct pharmacy_id) as pharmacies_count, max(word_similarity(?, base_name)) as score
            from price_items
            where price_upload_id = ? and base_name <> '' and ? <% base_name
            group by base_name
            order by score desc, pharmacies_count desc, base_name
            limit ?
            SQL;

        return DB::transaction(function () use ($sql, $upload, $term, $threshold, $limit): array {
            DB::select("select set_config('pg_trgm.word_similarity_threshold', ?, true)", [(string) $threshold]);

            return $this->nameRows(DB::select($sql, [$term, $upload->id, $term, $limit]));
        });
    }

    /**
     * How many pharmacies sell something in the upload.
     */
    public function countPharmacies(PriceUpload $upload): int
    {
        return (int) PriceItem::query()->whereBelongsTo($upload)->distinct()->count('pharmacy_id');
    }

    /**
     * @param  array<int, object{name: string, pharmacies_count: int|string}>  $rows
     * @return list<array{name: string, pharmacies_count: int}>
     */
    private function nameRows(array $rows): array
    {
        return array_map(fn (object $row): array => [
            'name' => (string) $row->name,
            'pharmacies_count' => (int) $row->pharmacies_count,
        ], array_values($rows));
    }

    /**
     * A LIKE pattern for values starting with the text, with its own wildcards escaped.
     */
    private function likePrefix(string $text): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text).'%';
    }

    /**
     * The first products of a sheet in their order in the file, for the review screen.
     *
     * @return Collection<int, PriceItem>
     */
    public function firstForSheet(PriceUploadSheet $sheet, int $limit): Collection
    {
        return PriceItem::query()
            ->where('price_upload_id', $sheet->price_upload_id)
            ->where('pharmacy_id', $sheet->pharmacy_id)
            ->orderBy('source_row')
            ->limit($limit)
            ->get();
    }
}
