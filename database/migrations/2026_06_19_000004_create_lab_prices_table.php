<?php

/** @see database/migrations/README.md — lab_prices table */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained('labs')->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained('treatments')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->cascadeOnDelete();
            $table->decimal('unit_cost', 12, 2);
            $table->string('currency', 3)->default('AED');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['treatment_id', 'doctor_id', 'lab_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_prices');
    }
};
