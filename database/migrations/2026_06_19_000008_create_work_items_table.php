<?php

/**
 * Parsed treatment lines from treatment_text (all valid codes).
 *
 * lab_jobs (000009) are created only when treatments.has_lab_cost = true.
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
        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_work_row_id')->constrained('daily_work_rows')->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained('treatments');
            $table->integer('quantity')->default(1);
            $table->integer('confidence')->default(100);
            $table->text('warning_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_items');
    }
};
