<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Not part of the original project skeleton: SESSION_DRIVER=database is
// already set (see .env.example) and used in production, but no migration
// for `sessions` ever existed in this repo, only `users`, `cache` and
// `jobs`. That means production's `sessions` table, if it exists, was
// created outside Laravel's migration history and is NOT recorded in the
// `migrations` table. `up()` checks for the table before creating it so
// that running `php artisan migrate` on that database does not fail (or
// try to recreate a table that is already there and may hold live
// sessions). See AGENTS.md, "Production database", for how to check this
// on the VPS before migrating.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        // Deliberately left empty: this migration cannot tell whether it
        // was the one that created `sessions` (up() may have found it
        // already there) or whether that table predates Laravel's
        // migration history entirely, so rolling back must never drop it.
        // A local/dev database is disposable via `docker compose down -v`
        // if a truly clean slate is ever needed instead.
    }
};
