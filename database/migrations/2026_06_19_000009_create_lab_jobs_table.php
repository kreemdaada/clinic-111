<?php

/**
 * Calculated lab cost (JOB) per lab-cost work_item.
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
        Schema::create('lab_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained('work_items')->cascadeOnDelete();
            $table->foreignId('lab_id')->constrained('labs');
            $table->foreignId('lab_price_id')->nullable()->constrained('lab_prices')->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost_aed', 12, 2)->default(0);
            $table->string('status')->default('calculated');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_jobs');
    }
};
