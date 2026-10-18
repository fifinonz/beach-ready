<?php

use Illuminate\Support\Facades\DB;

it('runs against the PostgreSQL test database, never the development one', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('beach_ready_testing');
});
