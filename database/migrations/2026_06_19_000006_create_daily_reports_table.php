<?php

/**
 * Imported daily/monthly accounting reports and pipeline status.
 *
 * Status: uploaded → parsed → calculated | needs_review → approved | failed
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
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('source_type');
            $table->string('source_file_name')->nullable();
            $table->string('status')->default('uploaded');
            $table->timestamps();

            $table->index('report_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
