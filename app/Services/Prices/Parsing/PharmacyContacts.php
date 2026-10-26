<?php

namespace App\Services\Prices\Parsing;

/**
 * Contacts a pharmacy writes above its price table.
 */
final readonly class PharmacyContacts
{
    /**
     * @param  list<string>  $phones  Numbers to dial, e.g. «+99312209785».
     */
    public function __construct(
        public array $phones = [],
        public ?string $address = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->phones === [] && $this->address === null;
    }
}
