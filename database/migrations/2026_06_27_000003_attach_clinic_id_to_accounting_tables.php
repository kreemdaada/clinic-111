<?php

/** @see database/migrations/README.md — attach clinic_id to accounting tables (Milestone 10, ADR-029) */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const ACCOUNTING_TABLES = [
        'daily_reports',
        'daily_work_rows',
        'payments',
        'work_items',
        'lab_jobs',
        'audit_logs',
    ];

    public function up(): void
    {
        $clinicId = $this->ensureDefaultClinicExists();

        foreach (self::ACCOUNTING_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('clinic_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('clinics')
                    ->cascadeOnDelete();
                $blueprint->index('clinic_id');
            });
        }

        DB::table('daily_reports')->whereNull('clinic_id')->update(['clinic_id' => $clinicId]);

        DB::table('daily_work_rows')
            ->whereNull('clinic_id')
            ->update([
                'clinic_id' => DB::raw('(SELECT clinic_id FROM daily_reports WHERE daily_reports.id = daily_work_rows.daily_report_id)'),
            ]);

        DB::table('daily_work_rows')->whereNull('clinic_id')->update(['clinic_id' => $clinicId]);

        DB::table('payments')
            ->whereNull('clinic_id')
            ->update([
                'clinic_id' => DB::raw('(SELECT clinic_id FROM daily_work_rows WHERE daily_work_rows.id = payments.daily_work_row_id)'),
            ]);

        DB::table('payments')->whereNull('clinic_id')->update(['clinic_id' => $clinicId]);

        DB::table('work_items')
            ->whereNull('clinic_id')
            ->update([
                'clinic_id' => DB::raw('(SELECT clinic_id FROM daily_work_rows WHERE daily_work_rows.id = work_items.daily_work_row_id)'),
            ]);

        DB::table('work_items')->whereNull('clinic_id')->update(['clinic_id' => $clinicId]);

        DB::table('lab_jobs')
            ->whereNull('clinic_id')
            ->update([
                'clinic_id' => DB::raw('(SELECT clinic_id FROM work_items WHERE work_items.id = lab_jobs.work_item_id)'),
            ]);

        DB::table('lab_jobs')->whereNull('clinic_id')->update(['clinic_id' => $clinicId]);

        DB::table('audit_logs')->whereNull('clinic_id')->update(['clinic_id' => $clinicId]);

        foreach (self::ACCOUNTING_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('clinic_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::ACCOUNTING_TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['clinic_id']);
                $blueprint->dropConstrainedForeignId('clinic_id');
            });
        }
    }

    private function ensureDefaultClinicExists(): int
    {
        $clinicId = DB::table('clinics')->where('code', 'CLINIC_111')->value('id');

        if ($clinicId !== null) {
            return (int) $clinicId;
        }

        return (int) DB::table('clinics')->insertGetId([
            'name' => 'Clinic 111',
            'code' => 'CLINIC_111',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'United Arab Emirates',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
