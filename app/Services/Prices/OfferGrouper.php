<?php

namespace App\Services\Prices;

use App\Enums\DosageForm;
use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * Groups the offers found for a drug into variants (dosage form, dose, pack size) and ranks the pharmacies
 * in each. Prices of different variants are never compared: «Азитромицин 500мг таб №3» and a 200мг/5мл
 * suspension are different products.
 *
 * A name often leaves out the form or the dose («Азитромицин 500 мг №3»). Such an offer joins the only
 * variant it can belong to (the same pack size, nothing contradicting); when it could belong to several,
 * it stays a variant of its own rather than being compared with the wrong product.
 */
final class OfferGrouper
{
    /**
     * @param  Collection<int, PriceItem>  $offers  Each pharmacy's cheapest offer per variant, every one with its "score".
     * @param  int  $perGroup  How many pharmacies each variant shows.
     * @param  float  $correctedBelow  A best score below this means the query was most likely misspelt.
     * @param  DosageForm|null  $form  The form named in the query: variants of other forms are left out.
     * @param  string|null  $dose  The dose named in the query, e.g. «500мг»: variants with other doses are left out.
     */
    public function group(
        Collection $offers,
        int $perGroup,
        float $correctedBelow,
        ?DosageForm $form = null,
        ?string $dose = null,
    ): PriceSearchResult {
        $variants = [];

        foreach ($offers as $offer) {
            $variant = $this->variantOf($offer);
            $variants[$this->key($variant)] = $variant;
        }

        $offersByVariant = [];

        foreach ($offers as $offer) {
            $offersByVariant[$this->targetKey($this->variantOf($offer), $variants)][] = $offer;
        }

        $groups = [];
        $pharmacyIds = [];
        $lowestPrice = null;

        foreach ($offersByVariant as $key => $variantOffers) {
            $variant = $variants[$key];

            if (! $this->matchesQuery($variant, $form, $dose)) {
                continue;
            }

            $ranked = $this->cheapestPerPharmacy($variantOffers);
            $shown = array_slice($ranked, 0, $perGroup);
            $cheapest = (float) $shown[0]->effective_price;

            foreach ($shown as $index => $offer) {
                $offer->setAttribute('place', $index + 1);
                $offer->setAttribute('price_difference', number_format((float) $offer->effective_price - $cheapest, 2, '.', ''));
            }

            foreach ($ranked as $offer) {
                $pharmacyIds[$offer->pharmacy_id] = true;
            }

            $lowestPrice = $lowestPrice === null || $cheapest < (float) $lowestPrice ? $shown[0]->effective_price : $lowestPrice;

            $groups[] = new PriceSearchGroup(
                drugName: $this->drugName($variantOffers),
                dosageForm: $variant['form'],
                dose: $variant['dose'],
                packCount: $variant['pack'],
                offers: new Collection($shown),
                pharmaciesCount: count($ranked),
                relevance: max(array_map(fn (PriceItem $offer): float => (float) $offer->getAttribute('score'), $variantOffers)),
            );
        }

        usort($groups, fn (PriceSearchGroup $a, PriceSearchGroup $b): int => [round($b->relevance, 2), $b->pharmaciesCount, $a->packCount ?? PHP_INT_MAX, $a->key()]
            <=> [round($a->relevance, 2), $a->pharmaciesCount, $b->packCount ?? PHP_INT_MAX, $b->key()]);

        $correctedTo = $groups !== [] && $groups[0]->relevance < $correctedBelow ? $groups[0]->drugName : null;

        return new PriceSearchResult($groups, count($pharmacyIds), $lowestPrice, $correctedTo);
    }

    /**
     * @return array{form: DosageForm|null, dose: string|null, pack: int|null}
     */
    private function variantOf(PriceItem $offer): array
    {
        return ['form' => $offer->dosage_form, 'dose' => $offer->dose, 'pack' => $offer->pack_count];
    }

    /**
     * @param  array{form: DosageForm|null, dose: string|null, pack: int|null}  $variant
     */
    private function key(array $variant): string
    {
        return implode('|', [$variant['form']->value ?? '', $variant['dose'] ?? '', $variant['pack'] ?? '']);
    }

    /**
     * The variant an offer is shown in: its own, or the only more complete variant it fits.
     *
     * @param  array{form: DosageForm|null, dose: string|null, pack: int|null}  $variant
     * @param  array<string, array{form: DosageForm|null, dose: string|null, pack: int|null}>  $variants
     */
    private function targetKey(array $variant, array $variants): string
    {
        if ($variant['form'] !== null && $variant['dose'] !== null) {
            return $this->key($variant);
        }

        $candidates = array_filter($variants, fn (array $other): bool => $this->refines($other, $variant));

        // Only the most complete candidates count: «таблетки, без дозы» is itself absorbed by «таблетки 500мг».
        $mostComplete = array_filter($candidates, function (array $candidate) use ($candidates): bool {
            foreach ($candidates as $other) {
                if ($this->refines($other, $candidate)) {
                    return false;
                }
            }

            return true;
        });

        return count($mostComplete) === 1 ? (string) array_key_first($mostComplete) : $this->key($variant);
    }

    /**
     * Whether $specific fills in what $general leaves out without contradicting it.
     *
     * @param  array{form: DosageForm|null, dose: string|null, pack: int|null}  $specific
     * @param  array{form: DosageForm|null, dose: string|null, pack: int|null}  $general
     */
    private function refines(array $specific, array $general): bool
    {
        return $specific !== $general
            && $specific['pack'] === $general['pack']
            && ($general['form'] === null || $general['form'] === $specific['form'])
            && ($general['dose'] === null || $general['dose'] === $specific['dose']);
    }

    /**
     * Variants the query rules out: «азитромицин суспензия» leaves out tablets. A variant whose names
     * do not say may still be it, so it stays.
     *
     * @param  array{form: DosageForm|null, dose: string|null, pack: int|null}  $variant
     */
    private function matchesQuery(array $variant, ?DosageForm $form, ?string $dose): bool
    {
        if ($form !== null && $variant['form'] !== null && $variant['form'] !== $form) {
            return false;
        }

        if ($dose === null || $variant['dose'] === null) {
            return true;
        }

        return array_diff(explode(' ', $dose), explode(' ', $variant['dose'])) === [];
    }

    /**
     * Cheapest first; a pharmacy whose offers of several merged variants met here counts once.
     *
     * @param  list<PriceItem>  $offers
     * @return non-empty-list<PriceItem>
     */
    private function cheapestPerPharmacy(array $offers): array
    {
        usort($offers, fn (PriceItem $a, PriceItem $b): int => [(float) $a->effective_price, -(float) $a->getAttribute('score'), $a->id]
            <=> [(float) $b->effective_price, -(float) $b->getAttribute('score'), $b->id]);

        $cheapest = [];

        foreach ($offers as $offer) {
            $cheapest[$offer->pharmacy_id] ??= $offer;
        }

        /** @var non-empty-list<PriceItem> */
        return array_values($cheapest);
    }

    /**
     * The leading words every product of the variant shares («азитромицин» for «азитромицин сандоз» and
     * «азитромицин велфарм»); the most frequent name when they share none (a brand next to its ingredient).
     *
     * @param  list<PriceItem>  $offers
     */
    private function drugName(array $offers): string
    {
        $names = array_values(array_filter(array_map(fn (PriceItem $offer): string => $offer->base_name, $offers)));

        if ($names === []) {
            return '';
        }

        $common = explode(' ', $names[0]);

        foreach ($names as $name) {
            $words = explode(' ', $name);
            $shared = 0;

            while ($shared < count($common) && ($words[$shared] ?? null) === $common[$shared]) {
                $shared++;
            }

            $common = array_slice($common, 0, $shared);
        }

        if ($common !== []) {
            return implode(' ', $common);
        }

        $counts = array_count_values($names);
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
