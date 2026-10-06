<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates (or resets) the two admin accounts the user asked for by name
 * (2026-10-06), both with the password "password". Deliberately never
 * called from DatabaseSeeder, in any environment: run it yourself when you
 * need it —
 *
 *     php artisan db:seed --class=AdminUsersSeeder
 *
 * (as `www-data`, like every other `artisan` call). See AGENTS.md for why
 * this stays out of the normal seeding flow, and `admin:create-user` for
 * changing a password without this seeder (the only way to reset one
 * user's password without touching the other's).
 */
class AdminUsersSeeder extends Seeder
{
    /**
     * @var array<string, string> email => display name shown in the panel
     */
    private const ACCOUNTS = [
        'peluqueriajenver@gmail.com' => 'Jenver',
        'josep@cobaprojects.com' => 'Josep',
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $email => $name) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password']
            );
        }
    }
}
