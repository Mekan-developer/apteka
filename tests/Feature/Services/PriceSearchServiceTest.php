<?php

use App\Models\PriceUpload;
use App\Services\Prices\PriceSearchService;

test('a query shorter than the minimum length searches nothing even when products match it', function () {
    $upload = PriceUpload::factory()->active()->create();
    offer($upload, 'Kamat', 'Ц таб №6', 1.5);

    $search = app(PriceSearchService::class);

    expect($search->search('ц')->groups)->toBe([])
        ->and($search->autocomplete('ц'))->toBe([]);
});
