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
     * The cheapest offers grouped by pack size. A query with «№N» looks only at that pack size.
     *
     * @return list<PriceSearchGroup>
     */
    public function search(string $query): array
    {
        $upload = $this->activeUpload();

        if ($upload === null) {
            return [];
        }

        $offers = $this->cheapestOffersIn($upload, $query, (int) config('prices.search.offers_per_pack'));

        $this->attachSheetDiscounts($upload, $offers);

        return array_values($offers
            ->groupBy(fn (PriceItem $offer): string => (string) $offer->pack_count)
            ->map(fn (Collection $group): PriceSearchGroup => new PriceSearchGroup($group->first()?->pack_count, $group->values()))
            ->all());
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
        $terms = [];

        foreach ($this->spellings($query) as $spelling) {
            $term = $this->term($spelling);
            array_push($terms, $term, ...$this->synonyms->namesRelatedTo($term));
        }

        return $this->items->cheapestOffers(
            upload: $upload,
            terms: array_values(array_unique($terms)),
            skeletons: array_values(array_unique(array_map(
                fn (string $spelling): string => $this->normalizer->normalize($spelling)->skeleton,
                $this->spellings($query),
            ))),
            packCount: $this->normalizer->normalize($query)->packCount,
            threshold: (float) config('prices.search.similarity_threshold'),
            relativeGap: (float) config('prices.search.relative_gap'),
            skeletonScore: (float) config('prices.search.skeleton_score'),
            perPack: $perPack,
        );
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
