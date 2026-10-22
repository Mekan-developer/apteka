<?php

namespace App\Services\Prices\Parsing;

/**
 * A goods discount (bonus in goods) stated for one product; it never changes the price.
 */
final readonly class ItemDiscount
{
    /**
     * @param  string|null  $percent  Decimal string such as "3.00"; null when the text gives no number.
     * @param  string|null  $note  Original wording when it is more than a plain number, e.g. «(+ товар.скидка)».
     */
    public function __construct(
        public ?string $percent,
        public ?string $note,
    ) {}
}
