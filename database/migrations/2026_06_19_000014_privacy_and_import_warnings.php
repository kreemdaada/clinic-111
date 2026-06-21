<?php

/**
 * Privacy + import validation schema.
 *
 * - Removes plain-text patient_name, mrn, file_number from daily_work_rows
 * - Adds patient_reference_hash, excel_row_number
 * - Creates daily_report_import_warnings
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
        Schema::table('daily_work_rows', function (Blueprint $table) {
            $table->dropColumn(['patient_name', 'mrn', 'file_number']);

            $table->string('patient_reference_hash', 64)->nullable()->after('work_date');
            $table->unsignedInteger('excel_row_number')->nullable()->after('patient_reference_hash');

            $table->index('patient_reference_hash');
        });

        Schema::create('daily_report_import_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->foreignId('daily_work_row_id')->nullable()->constrained('daily_work_rows')->cascadeOnDelete();
            $table->unsignedInteger('excel_row_number')->nullable();
            $table->string('doctor_code')->nullable();
            $table->text('treatment_text')->nullable();
            $table->string('warning_code');
            $table->text('message');
            $table->timestamps();

            $table->index(['daily_report_id', 'warning_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_import_warnings');

        Schema::table('daily_work_rows', function (Blueprint $table) {
            $table->dropIndex(['patient_reference_hash']);
            $table->dropColumn(['patient_reference_hash', 'excel_row_number']);

            $table->string('patient_name')->nullable();
            $table->string('mrn')->nullable();
            $table->string('file_number')->nullable();
        });
    }
};
