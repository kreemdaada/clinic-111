<?php

/** @see database/migrations/README.md — immutable treatment price snapshot per work item */

use App\Services\Accounting\WorkItemTreatmentSnapshotBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->string('treatment_code_snapshot')->nullable()->after('treatment_id');
            $table->decimal('treatment_price_original', 12, 2)->nullable()->after('treatment_code_snapshot');
            $table->char('treatment_price_currency', 3)->nullable()->after('treatment_price_original');
            $table->decimal('exchange_rate_to_aed', 12, 4)->nullable()->after('treatment_price_currency');
            $table->decimal('treatment_price_aed', 12, 2)->nullable()->after('exchange_rate_to_aed');
        });

        app(WorkItemTreatmentSnapshotBackfillService::class)->run();
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn([
                'treatment_code_snapshot',
                'treatment_price_original',
                'treatment_price_currency',
                'exchange_rate_to_aed',
                'treatment_price_aed',
            ]);
        });
    }
};
