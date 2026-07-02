<?php

/** @see database/migrations/2026_06_28_000003_create_treatment_prices_table.php — source of truth for down() */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('treatment_prices')) {
            $rowCount = DB::table('treatment_prices')->count();

            if ($rowCount > 0) {
                throw new RuntimeException(
                    'Cannot move treatment prices to treatments: treatment_prices contains '
                    .$rowCount.' row(s). Inspect data and create a manual migration plan before continuing.'
                );
            }
        }

        if (! Schema::hasColumn('treatments', 'treatment_price')) {
            Schema::table('treatments', function (Blueprint $table) {
                $table->decimal('treatment_price', 12, 2)->nullable()->after('requires_nurse_commission');
                $table->char('treatment_price_currency', 3)->nullable()->after('treatment_price');
            });
        }

        if (Schema::hasTable('treatment_prices')) {
            Schema::drop('treatment_prices');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('treatment_prices')) {
            Schema::create('treatment_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('treatment_id')->constrained('treatments')->cascadeOnDelete();
                $table->decimal('unit_price', 12, 2);
                $table->char('currency', 3);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['clinic_id', 'treatment_id', 'is_active']);
            });
        }

        if (Schema::hasColumn('treatments', 'treatment_price')) {
            Schema::table('treatments', function (Blueprint $table) {
                $table->dropColumn(['treatment_price', 'treatment_price_currency']);
            });
        }
    }
};
