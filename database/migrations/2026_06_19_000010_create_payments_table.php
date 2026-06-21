<?php

/** @see database/migrations/README.md — payments table (TOTAL source of truth) */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_work_row_id')->constrained('daily_work_rows')->cascadeOnDelete();
            $table->string('payment_method');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->decimal('amount_aed', 12, 2);
            $table->date('paid_at');
            $table->timestamps();

            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
