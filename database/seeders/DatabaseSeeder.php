<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Intentionally empty: the repository is public, so admin accounts are
     * never seeded (create them with `php artisan admin:create-user`), and
     * the salon enters its real services through the admin panel. The
     * default opening hours and booking settings come from their
     * migrations.
     */
    public function run(): void
    {
        //
    }
}
