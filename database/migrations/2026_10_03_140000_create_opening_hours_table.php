<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly opening ranges (up to two per day). The starting schedule is
     * the one already published in the HairSalon JSON-LD: Tuesday to
     * Saturday, 09:00-19:00, closed Sunday and Monday.
     */
    public function up(): void
    {
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            // ISO-8601 day of the week: 1 = Monday ... 7 = Sunday.
            $table->unsignedTinyInteger('weekday');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->index('weekday');
        });

        $now = now();

        DB::table('opening_hours')->insert(array_map(fn (int $weekday) => [
            'weekday' => $weekday,
            'opens_at' => '09:00:00',
            'closes_at' => '19:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ], [2, 3, 4, 5, 6]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_hours');
    }
};
