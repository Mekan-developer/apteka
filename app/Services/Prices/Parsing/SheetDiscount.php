<?php

namespace App\Services\Prices\Parsing;

use App\Enums\DiscountRuleType;

/**
 * A discount a pharmacy states for its whole sheet, e.g. «Товарная скидка 5%»
 * or «От суммы выше 10тыс манат финансовая скидка 5%» (with a minimum order sum).
 */
final readonly class SheetDiscount
{
    /**
     * @param  string  $percent  Decimal string such as "5.00".
     * @param  string|null  $minSum  Minimum order sum in manat as a decimal string.
     * @param  bool  $conditional  The discount needs something from the buyer: an order sum, payment in cash, five boxes.
     */
    public function __construct(
        public DiscountRuleType $type,
        public string $percent,
        public ?string $minSum,
        public string $rawText,
        public bool $conditional = false,
    ) {}

    /**
     * Only an unconditional price discount lowers the price search sorts by.
     */
    public function lowersPrice(): bool
    {
        return $this->type === DiscountRuleType::Price && ! $this->conditional;
    }
}
