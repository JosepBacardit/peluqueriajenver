<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Closures (holidays) and partial blocks (e.g. one hairdresser on
     * holiday) that reduce the booking capacity for a period.
     */
    public function up(): void
    {
        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // Appointments at a time subtracted from the capacity; null means
            // the salon is fully closed.
            $table->unsignedTinyInteger('capacity_reduction')->nullable();
            $table->string('reason', 150)->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_blocks');
    }
};
