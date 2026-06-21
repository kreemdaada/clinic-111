<?php

/**
 * Per-doctor Original Income Excel layout (sheet name, column letters).
 *
 * @see database/migrations/README.md
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_income_export_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->unique()->constrained('doctors')->cascadeOnDelete();
            $table->string('sheet_name');
            $table->string('layout');
            $table->unsignedSmallInteger('first_day_row')->default(2);
            $table->boolean('write_payment_headers')->default(false);
            $table->boolean('summary_shows_net_total')->default(false);
            $table->json('payment_columns')->nullable();
            $table->json('treatment_columns')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_income_export_profiles');
    }
};
