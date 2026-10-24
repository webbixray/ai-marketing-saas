<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds local pricing to plans. PlanController has always validated
     * amount/currency/interval from the admin form and pushed them to Stripe,
     * but never persisted them locally — so the plans table had no amount
     * and ReportingController had no local source of truth for MRR (it was
     * dividing the Stripe price ID string by 100, which is not a number).
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('amount')->default(0)->after('description');        // cents
            $table->string('currency', 3)->default('usd')->after('amount');
            $table->string('interval')->default('month')->after('currency');    // day|week|month|year
            $table->integer('interval_count')->default(1)->after('interval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['amount', 'currency', 'interval', 'interval_count']);
        });
    }
};
