<?php

use Illuminate\Support\Facades\DB;

test('the sqlite busy timeout is applied to the connection', function () {
    config(['database.connections.sqlite.busy_timeout' => 10000]);
    DB::purge('sqlite');

    expect(DB::connection('sqlite')->selectOne('PRAGMA busy_timeout')->timeout)->toBe(10000);
});
