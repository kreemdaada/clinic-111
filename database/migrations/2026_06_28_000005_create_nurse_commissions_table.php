<?php

/** @see database/migrations/README.md — nurse commission snapshot per work item (ADR-039) */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nurse_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('work_item_id')->unique()->constrained('work_items')->cascadeOnDelete();
            $table->foreignId('nurse_id')->constrained('nurses');
            $table->string('nurse_name_snapshot');
            $table->foreignId('treatment_id')->constrained('treatments');
            $table->string('treatment_code_snapshot');
            $table->string('treatment_name_snapshot');
            $table->decimal('treatment_price_original', 12, 2);
            $table->char('treatment_price_currency', 3);
            $table->decimal('exchange_rate_to_aed', 12, 4);
            $table->decimal('treatment_price_aed', 12, 2);
            $table->decimal('commission_percentage', 5, 2);
            $table->decimal('unit_commission_aed', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('total_commission_aed', 12, 2);
            $table->timestamps();

            $table->index('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nurse_commissions');
    }
};
