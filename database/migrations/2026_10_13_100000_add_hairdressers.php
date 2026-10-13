<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The salon's hairdressers (2026-10-13). The booking capacity is the
     * number of active ones, so this creates the two the salon has today,
     * Jennifer and Isabel (only their first names are ever shown to
     * customers), whatever booking_settings.capacity held.
     *
     * Additive only:
     * - appointments.hairdresser_id: null means "any hairdresser", the
     *   salon assigns it whenever it can (every existing appointment);
     * - appointments.hairdresser_requested: whether the customer chose
     *   that hairdresser herself (decides what she is shown and told);
     * - schedule_blocks.hairdresser_id: a block for one hairdresser only,
     *   stored with capacity_reduction = 1 so anything that only reads
     *   the reduction stays right.
     */
    public function up(): void
    {
        Schema::create('hairdressers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('hairdressers')->insert([
            ['name' => 'Jennifer', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Isabel', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('appointments', function (Blueprint $table) {
            // Hairdressers are never deleted (only deactivated).
            $table->foreignId('hairdresser_id')->nullable()->after('waits')->constrained()->restrictOnDelete();
            $table->boolean('hairdresser_requested')->default(false)->after('hairdresser_id');
        });

        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->foreignId('hairdresser_id')->nullable()->after('capacity_reduction')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hairdresser_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hairdresser_id');
            $table->dropColumn('hairdresser_requested');
        });

        Schema::dropIfExists('hairdressers');
    }
};
