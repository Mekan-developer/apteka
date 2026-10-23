<?php

namespace App\Services\Prices;

use App\Models\DiscountRule;
use App\Models\PriceItem;
use App\Models\PriceUpload;
use App\Repositories\DiscountRuleRepository;
use App\Repositories\DrugSynonymRepository;
use App\Repositories\PriceItemRepository;
use App\Repositories\PriceUploadRepository;
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

        $name = $this->normalizer->normalize($query);
        $term = $this->term($query);

        $offers = $this->items->cheapestOffers(
            upload: $upload,
            terms: [$term, ...$this->synonyms->namesRelatedTo($term)],
            skeleton: $name->skeleton,
            packCount: $name->packCount,
            threshold: (float) config('prices.search.similarity_threshold'),
            relativeGap: (float) config('prices.search.relative_gap'),
            skeletonScore: (float) config('prices.search.skeleton_score'),
            perPack: (int) config('prices.search.offers_per_pack'),
        );

        $this->attachSheetDiscounts($upload, $offers);

        return array_values($offers
            ->groupBy(fn (PriceItem $offer): string => (string) $offer->pack_count)
            ->map(fn (Collection $group): PriceSearchGroup => new PriceSearchGroup($group->first()?->pack_count, $group->values()))
            ->all());
    }

    /**
     * Product names close to a query that found nothing.
     *
     * @return list<string>
     */
    public function suggestions(string $query): array
    {
        $upload = $this->activeUpload();

        return $upload === null
            ? []
            : $this->items->closestBaseNames(
                $upload,
                $this->term($query),
                (float) config('prices.search.suggestion_threshold'),
                (int) config('prices.search.suggestions'),
            );
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
