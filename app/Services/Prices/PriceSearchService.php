<?php

namespace App\Services\Prices;

use App\Models\DiscountRule;
use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Repositories\DiscountRuleRepository;
use App\Repositories\DrugSynonymRepository;
use App\Repositories\PriceItemRepository;
use App\Repositories\PriceUploadRepository;
use App\Services\Prices\Parsing\LatinTransliterator;
use App\Services\Prices\Parsing\NameNormalizer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds the cheapest pharmacies for a drug in the active price upload (docs/price-search.md §6).
 * The query is normalized exactly like the product names were on import.
 */
class PriceSearchService
{
    private ?PriceUpload $activeUpload = null;

    private bool $activeUploadLoaded = false;

    public function __construct(
        private readonly NameNormalizer $normalizer,
        private readonly PriceUploadRepository $uploads,
        private readonly PriceItemRepository $items,
        private readonly DiscountRuleRepository $discountRules,
        private readonly DrugSynonymRepository $synonyms,
        private readonly LatinTransliterator $transliterator,
        private readonly OfferGrouper $grouper,
    ) {}

    public function activeUpload(): ?PriceUpload
    {
        if (! $this->activeUploadLoaded) {
            $this->activeUpload = $this->uploads->findActive();
            $this->activeUploadLoaded = true;
        }

        return $this->activeUpload;
    }

    /**
     * The cheapest pharmacies for every variant of the drug (dosage form, dose, pack size). A pack size,
     * form or dose named in the query («цитрамон №10», «азитромицин суспензия») narrows the variants.
     */
    public function search(string $query): PriceSearchResult
    {
        $upload = $this->activeUpload();

        if ($upload === null || ! $this->isSearchable($query)) {
            return PriceSearchResult::empty();
        }

        $name = $this->normalizer->normalize($query);

        $result = $this->grouper->group(
            $this->items->cheapestOffersPerVariant(...$this->matchArguments($upload, $query)),
            perGroup: (int) config('prices.search.offers_per_pack'),
            correctedBelow: (float) config('prices.search.corrected_below'),
            form: $name->dosageForm,
            dose: $name->dose,
        );

        $this->attachSheetDiscounts($upload, new Collection(array_merge(...array_map(
            fn (PriceSearchGroup $group): array => $group->offers->all(),
            $result->groups,
        ))));

        return $result;
    }

    /**
     * How many pharmacies the active prices cover.
     */
    public function pharmaciesCount(): int
    {
        $upload = $this->activeUpload();

        return $upload === null ? 0 : $this->items->countPharmacies($upload);
    }

    /**
     * Whether the active prices are older than a week: the page warns that they may have changed.
     */
    public function pricesAreStale(): bool
    {
        $activatedAt = $this->activeUpload()?->activated_at;

        return $activatedAt !== null && $activatedAt->lt(now()->subDays((int) config('prices.search.stale_after_days')));
    }

    /**
     * The cheapest offer of every pack size in each of the last published uploads (active and archived),
     * found exactly like the public search finds it.
     */
    public function history(string $query): PriceHistory
    {
        $uploads = $this->uploads->recentPublished((int) config('prices.history.uploads'));
        $offersByPack = [];

        foreach ($uploads as $upload) {
            foreach ($this->cheapestOffersIn($upload, $query, 1) as $offer) {
                $offersByPack[(string) $offer->pack_count][$upload->id] = $offer;
            }
        }

        // Known pack sizes first, smallest first; products without a pack size (key '') last.
        uksort($offersByPack, fn (int|string $a, int|string $b): int => match (true) {
            $a === $b => 0,
            $a === '' => 1,
            $b === '' => -1,
            default => (int) $a <=> (int) $b,
        });

        $rows = [];

        foreach ($offersByPack as $packCount => $offers) {
            $rows[] = new PriceHistoryRow($packCount === '' ? null : (int) $packCount, $offers);
        }

        return new PriceHistory($uploads, $rows);
    }

    /**
     * @return Collection<int, PriceItem>
     */
    private function cheapestOffersIn(PriceUpload $upload, string $query, int $perPack): Collection
    {
        return $this->items->cheapestOffers(...$this->matchArguments($upload, $query), perPack: $perPack);
    }

    /**
     * How the repository finds the products relevant to a query: the query and its synonyms, the consonant
     * skeletons of every spelling, the pack size named in the query and the similarity settings.
     *
     * @return array{upload: PriceUpload, terms: non-empty-list<string>, skeletons: list<string>, packCount: int|null, threshold: float, relativeGap: float, skeletonScore: float}
     */
    private function matchArguments(PriceUpload $upload, string $query): array
    {
        $terms = [];

        foreach ($this->spellings($query) as $spelling) {
            $term = $this->term($spelling);
            array_push($terms, $term, ...$this->synonyms->namesRelatedTo($term));
        }

        /** @var non-empty-list<string> $terms */
        $terms = array_values(array_unique($terms));

        return [
            'upload' => $upload,
            'terms' => $terms,
            'skeletons' => array_values(array_unique(array_map(
                fn (string $spelling): string => $this->normalizer->normalize($spelling)->skeleton,
                $this->spellings($query),
            ))),
            'packCount' => $this->normalizer->normalize($query)->packCount,
            'threshold' => (float) config('prices.search.similarity_threshold'),
            'relativeGap' => (float) config('prices.search.relative_gap'),
            'skeletonScore' => (float) config('prices.search.skeleton_score'),
        ];
    }

    /**
     * Whether the query is long enough to search: one or two letters match nearly every product.
     */
    public function isSearchable(string $query): bool
    {
        return mb_strlen(trim($query)) >= (int) config('prices.search.min_query_length');
    }

    /**
     * Names for the search box as the visitor types: names starting with the query first, then — for a typo
     * in the first letters — names that only resemble it. Names are normalized as on import («но шпа»).
     *
     * @return list<array{name: string, pharmacies_count: int}>
     */
    public function autocomplete(string $query): array
    {
        $upload = $this->activeUpload();

        if ($upload === null || ! $this->isSearchable($query)) {
            return [];
        }

        $limit = (int) config('prices.search.autocomplete');
        $terms = array_values(array_unique(array_map(fn (string $spelling): string => $this->term($spelling), $this->spellings($query))));
        $names = [];

        foreach ($this->items->namesStartingWith($upload, $terms, $limit) as $name) {
            $names[$name['name']] = $name;
        }

        // Trigrams of a two-letter query resemble too many names to be useful.
        if (count($names) < $limit && mb_strlen(trim($query)) >= 3) {
            foreach ($terms as $term) {
                foreach ($this->items->namesResembling($upload, $term, (float) config('prices.search.suggestion_threshold'), $limit) as $name) {
                    $names[$name['name']] ??= $name;
                }
            }
        }

        return array_slice(array_values($names), 0, $limit);
    }

    /**
     * Product names close to a query that found nothing.
     *
     * @return list<string>
     */
    public function suggestions(string $query): array
    {
        $upload = $this->activeUpload();

        if ($upload === null) {
            return [];
        }

        $limit = (int) config('prices.search.suggestions');
        $names = [];

        foreach ($this->spellings($query) as $spelling) {
            array_push($names, ...$this->items->closestBaseNames(
                $upload,
                $this->term($spelling),
                (float) config('prices.search.suggestion_threshold'),
                $limit,
            ));
        }

        return array_slice(array_values(array_unique($names)), 0, $limit);
    }

    /**
     * The query as typed and, when it has Latin words, spelled in Cyrillic: «analgin» also searches «аналгин»,
     * while a brand stored in Latin («ACTION PLUS») is still found by its own spelling.
     *
     * @return non-empty-list<string>
     */
    private function spellings(string $query): array
    {
        $cyrillic = $this->transliterator->toCyrillic($query);

        return $cyrillic === null ? [$query] : [$query, $cyrillic];
    }

    /**
     * The words compared with product names: the base name, or the whole normalized query
     * when it consists only of form words or numbers.
     */
    private function term(string $query): string
    {
        $name = $this->normalizer->normalize($query);

        return $name->baseName !== '' ? $name->baseName : $name->normalized;
    }

    /**
     * Notes such as «Товарная скидка 5%» or «от 10 000 ман −5%» are shown next to every offer of the pharmacy.
     *
     * @param  Collection<int, PriceItem>  $offers
     */
    private function attachSheetDiscounts(PriceUpload $upload, Collection $offers): void
    {
        $pharmacyIds = array_values(array_unique(array_map(fn (PriceItem $offer): int => $offer->pharmacy_id, $offers->all())));
        $discounts = $pharmacyIds === []
            ? collect()
            : $this->discountRules->forPharmacies($upload, $pharmacyIds)->groupBy('pharmacy_id');

        foreach ($offers as $offer) {
            /** @var Collection<int, DiscountRule> $pharmacyDiscounts */
            $pharmacyDiscounts = $discounts->get($offer->pharmacy_id, new Collection);
            $offer->setRelation('sheetDiscounts', $pharmacyDiscounts);
        }
    }
}
