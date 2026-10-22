<?php

namespace App\Services\Prices\Parsing;

use DateInterval;
use DateTimeInterface;

/**
 * A fingerprint of a header row. A pharmacy's remembered column mapping is applied automatically
 * only while its sheet header still has the same text.
 */
final class HeaderSignature
{
    /**
     * @param  list<bool|DateInterval|DateTimeInterface|float|int|string|null>  $values
     */
    public static function of(array $values): string
    {
        $labels = array_map(
            fn (mixed $value): string => is_string($value) ? mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value))) : '',
            $values,
        );

        return hash('sha256', implode('|', $labels));
    }
}
