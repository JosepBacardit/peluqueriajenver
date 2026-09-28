<?php

use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('sessions');
});

afterEach(function () {
    Schema::dropIfExists('sessions');
});

test('the sessions migration creates the table when it does not exist', function () {
    $migration = require database_path('migrations/0001_01_01_000003_create_sessions_table.php');

    $migration->up();

    expect(Schema::hasTable('sessions'))->toBeTrue();
    expect(Schema::getColumnListing('sessions'))->toContain('id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity');
});

test('the sessions migration does not fail when the table already exists', function () {
    $migration = require database_path('migrations/0001_01_01_000003_create_sessions_table.php');
    $migration->up();

    // Simulates production, where `sessions` may already exist without a
    // matching entry in the `migrations` table: running up() again must be
    // a no-op, not an error or a table recreation.
    $migration->up();

    expect(Schema::hasTable('sessions'))->toBeTrue();
});

test('the sessions migration never drops the table on rollback', function () {
    $migration = require database_path('migrations/0001_01_01_000003_create_sessions_table.php');
    $migration->up();

    $migration->down();

    expect(Schema::hasTable('sessions'))->toBeTrue();
});
