<?php

namespace App\Services\Prices\Parsing;

enum RowType
{
    /** A row with a product name and a recognised price. */
    case Product;

    /** Any other row: a separator such as «Индия» or «ABBOTT», a discount line, a repeated header. */
    case Text;
}
