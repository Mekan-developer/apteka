<?php

namespace App\Services\Prices\Parsing;

use DateInterval;
use DateTimeInterface;

/**
 * Finds the phones and the address a pharmacy writes above its price table: «заявки по тел: +993 71 24 14 29»,
 * «Адрес : г.Ашгабат, ул. 1946 Анкара», «Моб. +99364028211 Тел. раб.934544».
 *
 * Numbers are read as Turkmen ones: «+993 6X XXXXXX» and «8 6X XXXXXX» are mobiles, a bare six-digit number
 * is an Ashgabat landline (code 12) — the suppliers are in Ashgabat. Dates, order sums and postcodes are not phones.
 */
final class ContactExtractor
{
    private const int MAX_PHONES = 3;

    private const int MAX_ADDRESS_LENGTH = 255;

    /** A run of digits with the separators phones are written with: «+993 (65) 63-12-44», «20-97-85». */
    private const string PHONE = '/(?<![\p{L}\d])\(?\+?\(?\d[\d\s\-()]*\d/u';

    /** Words that start an address, in Russian and Turkmen. */
    private const string ADDRESS_START = '~(?<![\p{L}])(?:г\.\s*(?:ашхабад|ашгабат)|ашхабад|ашгабат|aşgabat|мкр\.?|ул\.|улица|проспект|köçesi|şaýoly)~iu';

    /** The city alone is no address: every supplier is in Ashgabat. */
    private const string CITY = '~(?:туркменистан|г\.\s*|ашхабад|ашгабат|aşgabat|şäheri|[^\p{L}])+~iu';

    /** Labels that end an address written on one line with the phones or the e-mail. */
    private const string ADDRESS_END = '~\s*(?:тел|tel|моб|факс|fax|e-?mail|эл\.|почта|\+993)~iu';

    /**
     * @param  array<int, list<bool|DateInterval|DateTimeInterface|float|int|string|null>>  $rows  Rows above the table and its header.
     */
    public function extract(array $rows): PharmacyContacts
    {
        $phones = [];
        $address = null;

        foreach ($rows as $values) {
            foreach ($values as $value) {
                $text = $this->text($value);

                if ($text === null) {
                    continue;
                }

                foreach ($this->phones($text) as $phone) {
                    $phones[$phone] = $phone;
                }

                $address ??= $this->address($text);
            }
        }

        return new PharmacyContacts(array_slice(array_values($phones), 0, self::MAX_PHONES), $address);
    }

    /**
     * A cell as text; a number typed without separators (99364320482) is stored by Excel as a number.
     */
    private function text(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => trim(str_replace("\u{A0}", ' ', $value)),
            is_int($value) => (string) $value,
            is_float($value) && floor($value) === $value && $value >= 100000 => sprintf('%.0f', $value),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function phones(string $text): array
    {
        $phones = [];

        // «+99364705550 +99363300359», «75-36-08, +993 65 447052», «45 49 52 / 45 49 54»: one number per piece.
        foreach (preg_split('~[,;/|]|(?=\+)|\s{2,}~u', $text) ?: [] as $piece) {
            preg_match_all(self::PHONE, $piece, $matches);

            foreach ($matches[0] as $match) {
                $dial = $this->dial((string) preg_replace('/\D/', '', $match));

                if ($dial !== null) {
                    $phones[] = $dial;
                }
            }
        }

        return $phones;
    }

    /**
     * The number to dial, or null when the digits are not a Turkmen phone number.
     */
    private function dial(string $digits): ?string
    {
        return match (true) {
            strlen($digits) === 11 && str_starts_with($digits, '993') => '+'.$digits,
            // 8 is the trunk prefix: «8 (62) 65-00-25».
            strlen($digits) === 9 && $digits[0] === '8' => '+993'.substr($digits, 1),
            // A mobile without the country code: «65 974444». Dates such as «19 10 2026» start with other digits.
            strlen($digits) === 8 && in_array($digits[0], ['6', '7'], true) => '+993'.$digits,
            // An Ashgabat landline; postcodes («744000») and round order sums end in 000.
            strlen($digits) === 6 && $digits[0] !== '0' && ! str_ends_with($digits, '000') => '+99312'.$digits,
            default => null,
        };
    }

    private function address(string $text): ?string
    {
        // «Эл.адрес: …» and «e-mail адрес: …» are e-mails.
        if (preg_match('/(?:^|[^\p{L}.])адрес\s*:?\s*(.+)$/iu', $text, $matches) === 1 && ! str_contains($matches[1], '@')) {
            return $this->cleanAddress($matches[1]);
        }

        if (preg_match(self::ADDRESS_START, $text, $matches, PREG_OFFSET_CAPTURE) === 1) {
            return $this->cleanAddress(substr($text, $matches[0][1]));
        }

        return null;
    }

    private function cleanAddress(string $text): ?string
    {
        $address = trim((preg_split(self::ADDRESS_END, $text) ?: [''])[0], " \t,;:|-");

        if (mb_strlen((string) preg_replace(self::CITY, '', $address)) < 4) {
            return null;
        }

        return mb_substr($address, 0, self::MAX_ADDRESS_LENGTH);
    }
}
