<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waits inside a service (e.g. a dye's processing time), during which
     * the hairdresser is free for someone else: a JSON list of
     * {"start": minutes from the start, "minutes": length}, or null for
     * none (App\Booking\TimeProfile).
     *
     * - services.waits: what the salon sets in the panel.
     * - appointment_services.waits: each service's copy, frozen at booking
     *   time like its duration.
     * - appointments.waits: the whole appointment's waits, measured from
     *   its start (its services chained), written with ends_at so the
     *   availability check and the agenda need no extra query.
     *
     * Additive only: existing services and appointments keep null (no
     * waits) and behave exactly as before.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('waits')->nullable()->after('duration_minutes');
        });

        Schema::table('appointment_services', function (Blueprint $table) {
            $table->json('waits')->nullable()->after('duration_minutes');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->json('waits')->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('waits');
        });

        Schema::table('appointment_services', function (Blueprint $table) {
            $table->dropColumn('waits');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('waits');
        });
    }
};
