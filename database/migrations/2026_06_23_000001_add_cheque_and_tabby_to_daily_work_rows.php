<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_work_rows', function (Blueprint $table) {
            $table->decimal('cheque_amount', 12, 2)->default(0)->after('dhs_amount');
            $table->decimal('tabby_amount', 12, 2)->default(0)->after('cheque_amount');
        });
    }

    public function down(): void
    {
        Schema::table('daily_work_rows', function (Blueprint $table) {
            $table->dropColumn(['cheque_amount', 'tabby_amount']);
        });
    }
};
