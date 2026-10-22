<?php

namespace App\Enums;

/**
 * Price discounts lower the price; goods discounts are a bonus in goods and never change the price.
 */
enum DiscountRuleType: string
{
    case Price = 'price';
    case Goods = 'goods';
}
