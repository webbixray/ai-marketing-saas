<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('campaign_performance_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('spend', 10, 2)->default(0);
            $table->decimal('conversion_value', 15, 2)->default(0); // revenue
            $table->decimal('ctr', 5, 2)->default(0);
            $table->decimal('cvr', 5, 2)->default(0);
            $table->decimal('cpc', 10, 2)->default(0);
            $table->decimal('cpa', 10, 2)->default(0);
            $table->decimal('roas', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'date']);
            $table->index(['date', 'campaign_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_performance_daily');
    }
};
