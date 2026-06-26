<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_fixed_fees', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('valid_to');
            $table->dropUnique(['doctor_id', 'treatment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('doctor_fixed_fees', function (Blueprint $table) {
            $table->dropColumn('is_active');
            $table->unique(['doctor_id', 'treatment_id']);
        });
    }
};
