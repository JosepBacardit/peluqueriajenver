<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row table with the booking rules the salon can edit from the
     * admin panel, created with the values agreed with the user.
     */
    public function up(): void
    {
        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('capacity');
            $table->unsignedSmallInteger('slot_interval_minutes');
            $table->unsignedSmallInteger('min_notice_minutes');
            $table->unsignedSmallInteger('max_advance_days');
            $table->unsignedSmallInteger('cancellation_limit_hours');
            // The "Reserva online activa" switch (2026-10-06): on by
            // default, so installing or seeding never silently turns
            // booking off. These migrations have never run in
            // production, so the column is added here rather than in a
            // separate migration (same precedent as T046).
            $table->boolean('online_booking_enabled')->default(true);
            $table->timestamps();
        });

        DB::table('booking_settings')->insert([
            'capacity' => 2,
            'slot_interval_minutes' => 15,
            'min_notice_minutes' => 120,
            'max_advance_days' => 60,
            'cancellation_limit_hours' => 24,
            'online_booking_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_settings');
    }
};
