<?php

/** @see database/migrations/README.md — nurse commission eligibility flag (ADR-039) */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->boolean('requires_nurse_commission')->default(false)->after('has_lab_cost');
        });
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn('requires_nurse_commission');
        });
    }
};
