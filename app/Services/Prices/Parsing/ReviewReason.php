<?php

namespace App\Services\Prices\Parsing;

/**
 * Why a sheet needs an admin to choose its columns by hand.
 */
enum ReviewReason: string
{
    case ColumnsNotDetected = 'columns_not_detected';
    case NoProducts = 'no_products';
    case FewPrices = 'few_prices';
}
