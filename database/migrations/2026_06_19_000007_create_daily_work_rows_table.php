<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_work_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors');
            $table->date('work_date');
            $table->string('patient_name')->nullable();
            $table->string('mrn')->nullable();
            $table->string('file_number')->nullable();
            $table->text('treatment_text')->nullable();
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('dhs_amount', 12, 2)->default(0);
            $table->decimal('usd_amount', 12, 2)->default(0);
            $table->decimal('usd_to_aed_amount', 12, 2)->default(0);
            $table->decimal('visa_amount', 12, 2)->default(0);
            $table->decimal('paid_total_aed', 12, 2)->default(0);
            $table->decimal('balance_dhs', 12, 2)->default(0);
            $table->decimal('balance_usd', 12, 2)->default(0);
            $table->integer('crown_count')->default(0);
            $table->json('raw_data_json')->nullable();
            $table->timestamps();

            $table->index(['daily_report_id', 'doctor_id']);
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_work_rows');
    }
};
