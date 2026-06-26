<?php

/** @see database/migrations/README.md — per-clinic unique codes (Milestone 11 fix) */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'treatments',
        'labs',
        'doctors',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropUnique("{$tableName}_code_unique");
                $table->unique(['clinic_id', 'code'], "{$tableName}_clinic_id_code_unique");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropUnique("{$tableName}_clinic_id_code_unique");
                $table->unique('code', "{$tableName}_code_unique");
            });
        }
    }
};
