<?php

/** @see database/migrations/README.md — per-doctor lab JOB billing rules */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_lab_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained('treatments')->cascadeOnDelete();
            $table->boolean('bill_lab_job')->default(true);
            $table->timestamps();

            $table->unique(['doctor_id', 'treatment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_lab_billings');
    }
};
