<?php

use Illuminate\Support\Facades\DB;

test('tests run on the separate postgresql testing database', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('testing');
});
