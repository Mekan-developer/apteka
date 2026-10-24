<?php

namespace App\Services\Prices\Parsing;

/**
 * Spells a drug name typed in Latin letters in Cyrillic, so «analgin» finds «Анальгин».
 * Covers the English spelling and the Turkmen alphabet (ş, ç, ý, ž, ň, ä, ö, ü); the result
 * is approximate, and the fuzzy search tolerates the letters it gets wrong.
 */
final class LatinTransliterator
{
    /**
     * strtr() always replaces the longest match first, so «sh» wins over «s» and «ce» over «c».
     */
    private const array LETTERS = [
        'shch' => 'щ', 'sch' => 'щ', 'sh' => 'ш', 'ch' => 'ч', 'zh' => 'ж', 'kh' => 'х', 'ts' => 'ц',
        'ph' => 'ф', 'th' => 'т', 'ck' => 'к', 'qu' => 'кв',
        'ya' => 'я', 'yu' => 'ю', 'yo' => 'е', 'ye' => 'е', 'ýa' => 'я', 'ýu' => 'ю', 'ýo' => 'е', 'ýe' => 'е',
        // «c» sounds like «ц» before e, i and y (cefazolin, citramon) and like «к» elsewhere (coldrex).
        'ce' => 'це', 'ci' => 'ци', 'cy' => 'ци', 'c' => 'к',
        'a' => 'а', 'b' => 'б', 'd' => 'д', 'e' => 'е', 'f' => 'ф', 'g' => 'г', 'h' => 'х', 'i' => 'и',
        'j' => 'ж', 'k' => 'к', 'l' => 'л', 'm' => 'м', 'n' => 'н', 'o' => 'о', 'p' => 'п', 'q' => 'к',
        'r' => 'р', 's' => 'с', 't' => 'т', 'u' => 'у', 'v' => 'в', 'w' => 'в', 'x' => 'кс', 'y' => 'и', 'z' => 'з',
        'ş' => 'ш', 'ç' => 'ч', 'ý' => 'й', 'ž' => 'ж', 'ň' => 'н', 'ä' => 'е', 'ö' => 'е', 'ü' => 'у',
    ];

    /**
     * The name with its Latin words spelled in Cyrillic, or null when it has no Latin words.
     */
    public function toCyrillic(string $name): ?string
    {
        if (preg_match('/\p{Latin}/u', $name) !== 1) {
            return null;
        }

        return (string) preg_replace_callback(
            '/\p{Latin}+/u',
            fn (array $word): string => strtr(mb_strtolower($word[0]), self::LETTERS),
            $name,
        );
    }
}
