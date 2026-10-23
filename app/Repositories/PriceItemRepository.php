<?php

namespace App\Repositories;

use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Models\PriceUploadSheet;
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
     * candidates by whole-word trigram similarity of the base name or active ingredient (or by consonants,
     * for typos in vowels) → only those close to the best match → each pharmacy's cheapest offer per
     * pack size → the cheapest pharmacies. Every returned item carries its "score" and its "place".
     *
     * Whole words matter: plain word similarity puts «силденафил цитрат» at 0.56 to «цитрамон».
     * The relative cutoff keeps a cheaper look-alike («Баралгин» for «аналгин») from outranking
     * the drug itself once the drug is found.
     *
     * @return Collection<int, PriceItem>
     */
    public function cheapestOffers(
        PriceUpload $upload,
        string $term,
        string $skeleton,
        ?int $packCount,
        float $threshold,
        float $relativeGap,
        float $skeletonScore,
        int $perPack,
    ): Collection {
        // «ношпа» and «Но-шпа» («но шпа» once normalized) meet when spaces are ignored.
        $compactTerm = str_replace(' ', '', $term);

        $scoreSql = [
            'strict_word_similarity(?, pi.base_name)',
            'coalesce(strict_word_similarity(?, pi.active_ingredient), 0)',
            'strict_word_similarity(?, pi.name_compact)',
        ];
        $scoreBindings = [$term, $term, $compactTerm];
        $matchSql = ['? <<% pi.base_name', '? <<% pi.active_ingredient', '? <<% pi.name_compact'];
        $matchBindings = [$term, $term, $compactTerm];

        if (mb_strlen($skeleton) >= 3) {
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
        $filterBindings = [...$filterBindings, ...$matchBindings];

        $sql = <<<SQL
            with candidates as (
                select pi.*, greatest({$scoreSql}) as score
                from price_items pi
                where pi.price_upload_id = ?
                    {$packSql}
                    and ({$matchSql})
            ),
            relevant as (
                select *, max(score) over () as best_score
                from candidates
            ),
            cheapest_per_pharmacy as (
                select distinct on (pack_count, pharmacy_id) *
                from relevant
                where score >= greatest(?::real, best_score - ?::real)
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

        return DB::transaction(function () use ($sql, $scoreBindings, $filterBindings, $threshold, $relativeGap, $perPack): Collection {
            // "<<%" uses this threshold (pg_trgm's default is 0.5), which lets the GIN indexes serve the search.
            DB::select("select set_config('pg_trgm.strict_word_similarity_threshold', ?, true)", [(string) $threshold]);

            return PriceItem::fromQuery($sql, [...$scoreBindings, ...$filterBindings, $threshold, $relativeGap, $perPack])
                ->load('pharmacy');
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
