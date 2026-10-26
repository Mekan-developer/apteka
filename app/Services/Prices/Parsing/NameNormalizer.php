<?php

namespace App\Services\Prices\Parsing;

use App\Enums\DosageForm;

/**
 * Prepares a product name for search. The same normalization runs on import and on the search query,
 * so «Цитрамон-Боримед таб №6» and «ЦИТРАМОН - Боримед таблетки 200мг №6 Беларусь» meet in one base name.
 */
final class NameNormalizer
{
    /**
     * Latin letters that look like Cyrillic ones; replaced only inside words that also contain Cyrillic.
     */
    private const array LATIN_LOOKALIKES = [
        'a' => 'а', 'b' => 'в', 'c' => 'с', 'e' => 'е', 'h' => 'н', 'k' => 'к',
        'm' => 'м', 'o' => 'о', 'p' => 'р', 't' => 'т', 'x' => 'х', 'y' => 'у',
    ];

    private const array UNITS = [
        'мкг' => 'мкг', 'мг' => 'мг', 'мл' => 'мл', 'гр' => 'г', 'г' => 'г', 'ме' => 'ме', 'ед' => 'ед',
        'mcg' => 'мкг', 'mg' => 'мг', 'ml' => 'мл', 'g' => 'г', '%' => '%',
    ];

    private const string DOSE = '/(\d+(?:[.,]\d+)?)\s*(мкг|мг|мл|гр|г|ме|ед|mcg|mg|ml|g|%)(?!\p{L})/u';

    private const string PACK = '/(?:№|#|(?<!\p{L})n)\s*(\d+)/u';

    /**
     * Words naming the dosage form, checked in this order: the first form found wins, so «порошок д/приг суспензии»
     * is a suspension and «р-р д/ин» an injection, not a solution. Soft and hard signs are already gone («мазь» → «маз»).
     */
    private const array DOSAGE_FORMS = [
        'injection' => 'амп(?:ул\p{L}*)?|инек\p{L}*|инфуз\p{L}*|инф|д/ин\p{L}*|в/в|в/м|шприц\p{L}*',
        'suspension' => 'сусп\p{L}*',
        'syrup' => 'сироп\p{L}*',
        'drops' => 'капл\p{L}*|капел\p{L}*|кап',
        'spray' => 'спрей\p{L}*|аэроз\p{L}*',
        'suppositories' => 'супп\p{L}*|свеч\p{L}*',
        'ointment' => 'маз\p{L}*|гел|геля|эмулгел\p{L}*|крем|крема|балзам\p{L}*|линимент\p{L}*',
        'capsules' => 'капс\p{L}*',
        'tablets' => 'таб\p{L}*|драже',
        'powder' => 'пор|порош\p{L}*|саше|гранул\p{L}*',
        'solution' => 'р-р\p{L}*|раств\p{L}*',
    ];

    /**
     * Words that describe the dosage form, the package, the country or are plain prepositions:
     * they are left out of the base name so that different spellings of one drug meet.
     */
    private const array STOP_WORDS = [
        'таб', 'табл', 'таблетки', 'таблетка', 'таблеток', 'капс', 'капсулы', 'капсула', 'кап', 'капли',
        'сироп', 'раствор', 'раствора', 'растворе', 'растворитель', 'вода', 'мазь', 'гель', 'крем', 'эмульгель', 'амп', 'ампулы', 'ампула', 'ампулах', 'ампуле',
        'фл', 'флак', 'флакон', 'флаконе', 'флаконах', 'пор', 'порошок', 'саше', 'драже', 'супп', 'суппоз',
        'суппозитории', 'свечи', 'спрей', 'сусп', 'суспенз', 'суспензия', 'суспензии', 'эмульсия', 'гранулы', 'жев',
        'рект', 'ректальные', 'кишечнораств', 'кишечнорастворимые', 'внутрь', 'внутр', 'внут', 'прием', 'приема',
        'ин', 'инъек', 'введ', 'введения', 'комплекте',
        'шип', 'шипучие', 'пак', 'пакет', 'пакетики', 'туба', 'тубе', 'уп', 'упак', 'упаковка', 'шт',
        'наруж', 'наружн', 'наружного', 'прим', 'применения', 'инъекций', 'инф', 'инфузий', 'приготовления',
        'приг', 'назал', 'назальные', 'назальный', 'наз', 'глаз', 'глазные', 'ушные', 'ваг', 'лиоф',
        'концентрат', 'покрытые', 'оболочкой', 'обол', 'пленочной', 'для', 'детей', 'детск', 'взр', 'взрослых',
        'во', 'в', 'и', 'с', 'д', 'на', 'от', 'по', 'мг', 'мкг', 'мл', 'г', 'гр', 'ед', 'ме', 'mg', 'ml',
        'россия', 'индия', 'беларусь', 'китай', 'германия', 'турция', 'пакистан', 'украина', 'венгрия',
        'польша', 'франция', 'словения', 'швейцария', 'греция', 'бельгия', 'нидерланды', 'румыния',
        'болгария', 'испания', 'италия', 'египет', 'иран', 'корея', 'латвия', 'литва', 'казахстан',
        'узбекистан', 'туркменистан', 'тм',
    ];

    /** @var array<string, int> */
    private readonly array $stopWords;

    public function __construct()
    {
        // Names lose their soft and hard signs, so the stop words must too: «беларусь» → «беларус».
        $this->stopWords = array_flip(str_replace(['ь', 'ъ'], '', self::STOP_WORDS));
    }

    public function normalize(string $name): NormalizedName
    {
        $text = $this->withoutTranslation($this->prepare($name));
        $baseName = $this->baseName($text);

        return new NormalizedName(
            normalized: $this->withoutPunctuation($text),
            skeleton: $this->skeleton($baseName),
            baseName: $baseName,
            activeIngredient: $this->activeIngredient($text),
            packCount: preg_match(self::PACK, $text, $matches) === 1 ? (int) $matches[1] : null,
            dose: $this->dose($text),
            dosageForm: $this->dosageForm($text),
        );
    }

    private function dosageForm(string $text): ?DosageForm
    {
        foreach (self::DOSAGE_FORMS as $form => $words) {
            if (preg_match('~(?<!\p{L})(?:'.$words.')(?!\p{L})~u', $text) === 1) {
                return DosageForm::from($form);
            }
        }

        return null;
    }

    /**
     * Lowercase, «ё» → «е», no soft or hard signs («Анальгин» and «Аналгин» are both written),
     * Latin lookalikes inside Cyrillic words → Cyrillic, single spaces.
     */
    private function prepare(string $name): string
    {
        $text = str_replace(['ё', 'ь', 'ъ', "\u{A0}", '®', '™'], ['е', '', '', ' ', '', ''], mb_strtolower($name));

        $text = (string) preg_replace_callback(
            '/\p{L}+/u',
            fn (array $word): string => preg_match('/\p{Cyrillic}/u', $word[0]) === 1
                ? strtr($word[0], self::LATIN_LOOKALIKES)
                : $word[0],
            $text,
        );

        return $this->collapse($text);
    }

    /**
     * Bilingual names keep only the Russian part: «Ампициллин … №1 / AMPICILLIN SODIUM …».
     */
    private function withoutTranslation(string $text): string
    {
        return preg_match('~^(.*?\p{Cyrillic}.*?)\s*/\s*\p{Latin}~u', $text, $matches) === 1
            ? trim($matches[1])
            : $text;
    }

    private function withoutPunctuation(string $text): string
    {
        $text = (string) preg_replace('/(?<=\d),(?=\d)/u', '.', $text);
        $text = (string) preg_replace('/[^\p{L}\p{N}%№.]+/u', ' ', $text);

        return $this->collapse((string) preg_replace('/(?<!\d)\.|\.(?!\d)/u', ' ', $text));
    }

    private function baseName(string $text): string
    {
        $text = (string) preg_replace('/\([^()]*\)?/u', ' ', $text);
        $text = (string) preg_replace('~(?<!\p{L})(?:р-р\p{L}*|\p{L}{1,2}/\p{L}{1,6}\.?)~u', ' ', $text);

        $words = $this->meaningfulWords($text);

        if ($words !== []) {
            return implode(' ', $words);
        }

        return preg_match('/\p{L}+/u', $text, $matches) === 1 ? $matches[0] : $this->withoutPunctuation($text);
    }

    private function activeIngredient(string $text): ?string
    {
        preg_match_all('/\(([^()]*)\)/u', $text, $matches);

        foreach ($matches[1] as $content) {
            $words = $this->meaningfulWords($content);

            if ($words !== []) {
                return implode(' ', $words);
            }
        }

        return null;
    }

    /**
     * Every dose in one spelling, so equal doses meet: «2,0 мл» → «2мл», and the strength comes before
     * the volume — «2мл 500мг» → «500мг 2мл», while «200мг/5мл 20мл» keeps its order.
     */
    private function dose(string $text): ?string
    {
        preg_match_all(self::DOSE, $text, $matches, PREG_SET_ORDER);

        $doses = array_values(array_unique(array_map(
            fn (array $match): string => $this->number($match[1]).self::UNITS[$match[2]],
            $matches,
        )));

        // usort is stable: doses of the same kind keep their order in the name.
        usort($doses, fn (string $a, string $b): int => (int) str_ends_with($a, 'мл') <=> (int) str_ends_with($b, 'мл'));

        return $doses === [] ? null : implode(' ', $doses);
    }

    /**
     * «2,50» → «2.5», «2.0» → «2».
     */
    private function number(string $number): string
    {
        $number = str_replace(',', '.', $number);

        return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
    }

    /**
     * Consonants only with repeats collapsed: an extra signal for typos in vowels.
     */
    private function skeleton(string $baseName): string
    {
        $consonants = (string) preg_replace('/[^бвгджзйклмнпрстфхцчшщbcdfghjklmnpqrstvwxz]/u', '', $baseName);

        return (string) preg_replace('/(.)\1+/u', '$1', $consonants);
    }

    /**
     * @return list<string>
     */
    private function meaningfulWords(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $words,
            fn (string $word): bool => preg_match('/\p{N}/u', $word) !== 1 && ! isset($this->stopWords[$word]),
        ));
    }

    private function collapse(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
