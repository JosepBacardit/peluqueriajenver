<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            // Its 1 to Appointment::MAX_SERVICES services, frozen at booking
            // time, live in appointment_services. This is their names joined
            // with " + " in the same order (5 names of up to 100 characters
            // plus separators fit in 512), written in the same statement as
            // the services so the agenda, its cards and the emails can show
            // them without loading the services.
            $table->string('services_label', 512);
            // Salon-local times (the app runs on Europe/Madrid).
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('customer_name', 100);
            $table->string('customer_phone', 20);
            $table->string('customer_email', 150)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('status', 20);
            $table->string('source', 20);
            // Personal, unguessable link to the appointment page.
            $table->string('token', 64)->unique();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->timestamp('customer_notified_at')->nullable();
            $table->timestamp('salon_notified_at')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
            $table->index(['customer_email', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
