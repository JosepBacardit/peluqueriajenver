<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The services each hairdresser stands out in ("Destaca en", PRF-173):
     * shown to customers and suggested in the panel, never used to decide
     * availability. Additive only: it starts empty.
     */
    public function up(): void
    {
        Schema::create('hairdresser_service', function (Blueprint $table) {
            $table->id();
            // Neither is ever deleted (only deactivated); the cascade only
            // keeps the table consistent if one ever were.
            $table->foreignId('hairdresser_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->unique(['hairdresser_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hairdresser_service');
    }
};
