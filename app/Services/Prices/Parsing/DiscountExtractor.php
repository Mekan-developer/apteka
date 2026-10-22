<?php

namespace App\Services\Prices\Parsing;

use App\Enums\DiscountRuleType;

/**
 * Finds the discounts pharmacies write into their sheets:
 * a goods discount column, a goods discount inside a product name,
 * a line for the whole sheet and discounts that depend on the order sum.
 */
final class DiscountExtractor
{
    /** «бонус 10 манат + подарок» is a promotion, not a percent discount, so only these words count. */
    private const string DISCOUNT_WORD = '/скидк|нагрузк/u';

    private const string GOODS_WORD = '/товар|нагрузк/u';

    /** «при оплате наличными», «при покупке 5 коробок», «от суммы», «подарок», «при оплате сразу». */
    private const string CONDITION = '/(?<!\p{L})при(?!\p{L})|от\s+сумм|подар|сразу/u';

    private const string PERCENT = '/([+-]?)\s*"?(\d+(?:[.,]\d+)?)\s*"?\s*%/u';

    private const string ORDER_SUM = '/(\d{1,3}(?:\s\d{3})+|\d+(?:[.,]\d+)?)\s*(тыс\.?)?\s*(?:манат|ман|manat|man|tmt|тмт)/u';

    private const string ORDER_SUM_IN_THOUSANDS = '/(\d+(?:[.,]\d+)?)\s*тыс/u';

    private const string GOODS_DISCOUNT_IN_NAME = '/\(?\s*\+?\s*(?:(\d+(?:[.,]\d+)?)\s*%\s*)?товар\p{L}*\.?\s*скидк\p{L}*\s*\)?/iu';

    /**
     * Discounts stated by a line of text outside the product rows. A ladder such as
     * «20 000 ман -2% скидка / 30 000 ман -3% скидка» gives one discount per step.
     *
     * @return list<SheetDiscount>
     */
    public function fromSheetText(string $text): array
    {
        $rawText = trim((string) preg_replace('/\s+/u', ' ', $text));
        $lowercase = mb_strtolower(str_replace("\u{A0}", ' ', $rawText));

        if (preg_match(self::DISCOUNT_WORD, $lowercase) !== 1) {
            return [];
        }

        $type = preg_match(self::GOODS_WORD, $lowercase) === 1 ? DiscountRuleType::Goods : DiscountRuleType::Price;
        $hasCondition = preg_match(self::CONDITION, $lowercase) === 1;
        $discounts = [];

        foreach (preg_split('~\s*[/;]\s*~u', $lowercase) ?: [] as $step) {
            if (preg_match(self::PERCENT, $step, $matches) !== 1) {
                continue;
            }

            // "+10 %" is a surcharge (e.g. for cashless payment), unless it is extra goods («нагрузка +3%»).
            if ($matches[1] === '+' && $type !== DiscountRuleType::Goods) {
                continue;
            }

            $percent = $this->percent($matches[2]);

            if ($percent !== null) {
                $minSum = $this->orderSum($step);
                $discounts[] = new SheetDiscount($type, $percent, $minSum, $rawText, $hasCondition || $minSum !== null);
            }
        }

        return $discounts;
    }

    /**
     * A goods discount column cell: 0.03, 0.05, «10% товарный скидка», «без скидки».
     */
    public function fromGoodsCell(mixed $value): ?ItemDiscount
    {
        if (is_int($value) || is_float($value)) {
            $percent = $this->percent((string) ($value < 1 ? $value * 100 : $value));

            return $percent === null ? null : new ItemDiscount($percent, null);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (is_numeric(str_replace(',', '.', $text))) {
            return $this->fromGoodsCell((float) str_replace(',', '.', $text));
        }

        if (preg_match(self::PERCENT, $text, $matches) !== 1) {
            return null;
        }

        $percent = $this->percent($matches[2]);

        return $percent === null ? null : new ItemDiscount($percent, $text);
    }

    /**
     * A note next to one product in a column without a label, e.g. «нагрузка 10%».
     */
    public function fromItemNote(string $text): ?ItemDiscount
    {
        foreach ($this->fromSheetText($text) as $discount) {
            if ($discount->type === DiscountRuleType::Goods) {
                return new ItemDiscount($discount->percent, $discount->rawText);
            }
        }

        return null;
    }

    /**
     * A goods discount written into a product name, e.g. «Акбар №100 (+ товар.скидка)»
     * or «… 15% Товарная скидка». Returns the name without it and the discount.
     *
     * @return array{0: string, 1: ItemDiscount|null}
     */
    public function fromProductName(string $name): array
    {
        if (preg_match(self::GOODS_DISCOUNT_IN_NAME, $name, $matches) !== 1) {
            return [$name, null];
        }

        $cleanName = trim((string) preg_replace('/\s+/u', ' ', str_replace($matches[0], ' ', $name)));
        $percent = isset($matches[1]) ? $this->percent($matches[1]) : null;

        return [$cleanName, new ItemDiscount($percent, trim($matches[0]))];
    }

    private function orderSum(string $step): ?string
    {
        if (preg_match(self::ORDER_SUM, $step, $matches) === 1) {
            $amount = (float) str_replace([' ', ','], ['', '.'], $matches[1]);

            return number_format(($matches[2] ?? '') !== '' ? $amount * 1000 : $amount, 2, '.', '');
        }

        if (preg_match(self::ORDER_SUM_IN_THOUSANDS, $step, $matches) === 1) {
            return number_format((float) str_replace(',', '.', $matches[1]) * 1000, 2, '.', '');
        }

        return null;
    }

    private function percent(string $number): ?string
    {
        $percent = round((float) str_replace(',', '.', $number), 2);

        return $percent > 0 && $percent < 100 ? number_format($percent, 2, '.', '') : null;
    }
}
