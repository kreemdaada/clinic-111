<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->text('user_agent')->nullable()->after('ip_address');
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('approved_by');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            $table->text('unlock_reason')->nullable()->after('locked_by');
            $table->timestamp('unlocked_at')->nullable()->after('unlock_reason');
            $table->foreignId('unlocked_by')->nullable()->after('unlocked_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('lab_prices', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('valid_to');
        });
    }

    public function down(): void
    {
        Schema::table('lab_prices', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unlocked_by');
            $table->dropColumn(['unlocked_at', 'unlock_reason']);
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn('locked_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('user_agent');
        });
    }
};
