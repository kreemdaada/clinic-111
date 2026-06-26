<?php

/** @see database/migrations/README.md — attach clinic_id to configuration tables (Milestone 07) */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const CONFIGURATION_TABLES = [
        'users',
        'doctors',
        'labs',
        'treatments',
        'lab_prices',
        'doctor_fixed_fees',
    ];

    public function up(): void
    {
        $clinicId = $this->ensureDefaultClinicExists();

        foreach (self::CONFIGURATION_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('clinic_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('clinics')
                    ->cascadeOnDelete();
            });
        }

        foreach (self::CONFIGURATION_TABLES as $table) {
            DB::table($table)->whereNull('clinic_id')->update(['clinic_id' => $clinicId]);
        }

        foreach (self::CONFIGURATION_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('clinic_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::CONFIGURATION_TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
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
