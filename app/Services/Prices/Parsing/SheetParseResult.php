<?php

namespace App\Services\Prices\Parsing;

use App\Enums\PriceUploadSheetStatus;

final readonly class SheetParseResult
{
    /**
     * @param  list<ParsedItem>  $items
     * @param  list<SheetDiscount>  $discounts
     * @param  list<array{row: int, cells: list<bool|float|int|string|null>}>  $preview  First raw rows for the review screen.
     * @param  PharmacyContacts  $contacts  Phones and address written above the table.
     */
    public function __construct(
        public string $sheetName,
        public PriceUploadSheetStatus $status,
        public ?ColumnMapping $mapping,
        public array $items,
        public array $discounts,
        public ?ReviewReason $reviewReason,
        public array $preview,
        public PharmacyContacts $contacts = new PharmacyContacts,
    ) {}
}
