<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The services of each appointment (1 to Appointment::MAX_SERVICES),
     * done one after another in `position` order. Name, duration and price
     * are copied at booking time so later edits to a service never change
     * existing appointments; the price stays internal (never shown on a
     * public page or a customer email).
     */
    public function up(): void
    {
        Schema::create('appointment_services', function (Blueprint $table) {
            $table->id();
            // Appointments are never deleted (only cancelled); the cascade
            // only keeps the database consistent if one ever were.
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            // Services are never deleted (only deactivated), so this never dangles.
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('service_name', 100);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('price_cents')->nullable();
            $table->timestamps();

            $table->unique(['appointment_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_services');
    }
};
