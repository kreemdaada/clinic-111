<?php

/** @see database/migrations/README.md — patient/list price per treatment (ADR-039) */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained('treatments')->cascadeOnDelete();
            $table->decimal('unit_price', 12, 2);
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['clinic_id', 'treatment_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_prices');
    }
};
