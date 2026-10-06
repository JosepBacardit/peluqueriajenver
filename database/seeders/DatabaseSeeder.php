<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Admin accounts are never seeded here: the repository is public,
     * so creating or resetting one always stays a deliberate, manual
     * action — `php artisan admin:create-user`, or, only when explicitly
     * asked for, `php artisan db:seed --class=AdminUsersSeeder` (see
     * AGENTS.md). ServiceCatalogSeeder, in contrast, always runs: it is
     * the salon's real service catalogue (2026-10-06), not test data, and
     * safe to run again (idempotent by name). The default opening hours
     * and booking settings still come from their own migrations.
     */
    public function run(): void
    {
        $this->call(ServiceCatalogSeeder::class);
    }
}
