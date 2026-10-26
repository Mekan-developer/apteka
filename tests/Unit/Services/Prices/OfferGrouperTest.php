<?php

use App\Enums\DosageForm;
use App\Models\PriceItem;
use App\Services\Prices\OfferGrouper;
use App\Services\Prices\Parsing\NameNormalizer;
use App\Services\Prices\PriceSearchGroup;
use Illuminate\Database\Eloquent\Collection;

/**
 * An offer as the repository returns it: normalized like on import, with its search score.
 */
function foundOffer(int $pharmacyId, string $name, float $price, float $score = 1.0): PriceItem
{
    static $id = 0;
    $normalized = (new NameNormalizer)->normalize($name);

    return (new PriceItem)->forceFill([
        'id' => ++$id,
        'pharmacy_id' => $pharmacyId,
        'raw_name' => $name,
        'base_name' => $normalized->baseName,
        'dose' => $normalized->dose,
        'dosage_form' => $normalized->dosageForm,
        'pack_count' => $normalized->packCount,
        'price' => $price,
        'effective_price' => $price,
        'score' => $score,
    ]);
}

/**
 * @param  list<PriceItem>  $offers
 * @return list<PriceSearchGroup>
 */
function groupOffers(array $offers, ?DosageForm $form = null, ?string $dose = null): array
{
    return (new OfferGrouper)->group(new Collection($offers), 3, 0.75, $form, $dose)->groups;
}

/**
 * @param  list<PriceSearchGroup>  $groups
 * @return list<string>
 */
function variantKeys(array $groups): array
{
    return array_map(fn (PriceSearchGroup $group): string => $group->key(), $groups);
}

test('different doses and dosage forms of one drug are never compared', function () {
    $groups = groupOffers([
        foundOffer(1, 'Азитромицин таб 500мг №3', 12),
        foundOffer(2, 'Азитромицин Авексима 250 мг таб. №6', 9),
        foundOffer(3, 'Азитромицин Лек 200мг/5мл суспензия 200мл', 30),
        foundOffer(4, 'Азитромицин Сандоз 500мг таблетки №3', 13),
    ]);

    expect(variantKeys($groups))->toEqualCanonicalizing(['tablets|500мг|3', 'tablets|250мг|6', 'suspension|200мг 5мл 200мл|'])
        ->and($groups[0]->key())->toBe('tablets|500мг|3')
        ->and($groups[0]->offers->pluck('pharmacy_id')->all())->toBe([1, 4]);
});

test('a name without its form or dose joins the only variant it fits', function () {
    $groups = groupOffers([
        foundOffer(1, 'Азитромицин таб 500мг №3', 12),
        foundOffer(2, 'Азитромицин 500 мг. №3', 11),
        foundOffer(3, 'Азитромицин таб. №3', 14),
    ]);

    expect(variantKeys($groups))->toBe(['tablets|500мг|3'])
        ->and($groups[0]->offers->pluck('pharmacy_id')->all())->toBe([2, 1, 3]);
});

test('a name that fits several variants stays apart instead of being compared with the wrong one', function () {
    $groups = groupOffers([
        foundOffer(1, 'Азитромицин таб 500мг №6', 12),
        foundOffer(2, 'Азитромицин таб 250мг №6', 8),
        foundOffer(3, 'Азитромицин таб. №6', 5),
    ]);

    expect(variantKeys($groups))->toEqualCanonicalizing(['tablets|500мг|6', 'tablets|250мг|6', 'tablets||6']);
});

test('different pack sizes are never merged', function () {
    $groups = groupOffers([
        foundOffer(1, 'Цитрамон П №10', 2.6),
        foundOffer(2, 'Цитрамон-Боримед таб №6', 1.5),
    ]);

    expect(variantKeys($groups))->toBe(['tablets||6', '||10']);
});

test('each variant shows its three cheapest pharmacies once each, with the difference to the cheapest', function () {
    [$group] = groupOffers([
        foundOffer(1, 'Цитрамон таб 240мг №6', 1.5),
        foundOffer(1, 'Цитрамон таб. №6', 1.1),
        foundOffer(2, 'Цитрамон таб 240мг №6', 1.35),
        foundOffer(3, 'Цитрамон таб 240мг №6', 2.0),
        foundOffer(4, 'Цитрамон таб 240мг №6', 1.8),
    ]);

    expect($group->pharmaciesCount)->toBe(4)
        ->and($group->offers->map(fn (PriceItem $offer): array => [
            $offer->pharmacy_id,
            $offer->getAttribute('place'),
            $offer->getAttribute('price_difference'),
        ])->all())->toBe([[1, 1, '0.00'], [2, 2, '0.25'], [4, 3, '0.70']]);
});

test('variants are ordered by relevance, then by how many pharmacies sell them, then by pack size', function () {
    $groups = groupOffers([
        foundOffer(1, 'Аналгин таб 500мг №20', 3, score: 0.6),
        foundOffer(2, 'Аналгин таб 500мг №10', 2),
        foundOffer(3, 'Аналгин таб 500мг №6', 1.5),
        foundOffer(4, 'Аналгин таб 500мг №6', 1.4),
        foundOffer(5, 'Аналгин таб 500мг №4', 1),
    ]);

    expect(array_map(fn (PriceSearchGroup $group): ?int => $group->packCount, $groups))->toBe([6, 4, 10, 20]);
});

test('the form and dose named in the query leave out the other variants', function () {
    $offers = [
        foundOffer(1, 'Азитромицин таб 500мг №3', 12),
        foundOffer(2, 'Азитромицин Лек 200мг/5мл суспензия 200мл', 30),
        foundOffer(3, 'Азитромицин Авексима 250 мг таб. №6', 9),
    ];

    expect(variantKeys(groupOffers($offers, form: DosageForm::Suspension)))->toBe(['suspension|200мг 5мл 200мл|'])
        ->and(variantKeys(groupOffers($offers, dose: '500мг')))->toBe(['tablets|500мг|3']);
});

test('the drug name is what the names of a variant share', function () {
    [$group] = groupOffers([
        foundOffer(1, 'Азитромицин Сандоз 500мг таблетки №3', 13),
        foundOffer(2, 'Азитромицин Велфарм таб п/п/о 500мг №3', 12),
    ]);

    expect($group->drugName)->toBe('азитромицин');
});

test('the summary covers every variant and a misspelt query names the drug it found', function () {
    $result = (new OfferGrouper)->group(new Collection([
        foundOffer(1, 'Аналгин таб 500мг №10', 3.1, score: 0.6),
        foundOffer(2, 'Аналгин таб 500мг №20', 5.4, score: 0.6),
        foundOffer(3, 'Аналгин таб 500мг №20', 5.0, score: 0.6),
    ]), 3, 0.75);

    expect($result->pharmaciesCount)->toBe(3)
        ->and($result->lowestPrice)->toBe('3.10')
        ->and($result->correctedTo)->toBe('аналгин');
});

test('a query matched well is not reported as corrected', function () {
    $result = (new OfferGrouper)->group(new Collection([foundOffer(1, 'Аналгин таб 500мг №10', 3.1)]), 3, 0.75);

    expect($result->correctedTo)->toBeNull();
});
