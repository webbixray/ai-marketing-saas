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
        // Incrementality Tests
        Schema::create('incrementality_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('test_type'); // geo, user, time
            $table->json('treatment_campaigns');
            $table->json('control_campaigns')->nullable();
            $table->json('geo_regions_treatment')->nullable();
            $table->json('geo_regions_control')->nullable();
            $table->decimal('user_split_ratio', 3, 2)->default(0.50);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('primary_metric'); // conversions, revenue, roas
            $table->string('status')->default('draft'); // draft, running, paused, concluded
            $table->json('config')->nullable();
            $table->json('results')->nullable();
            $table->string('winner')->nullable(); // treatment, control, inconclusive
            $table->string('conclusion_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('concluded_at')->nullable();
            $table->timestamp('last_analyzed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index(['client_id', 'start_date']);
        });

        // Incrementality Assignments
        Schema::create('incrementality_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('incrementality_tests')->onDelete('cascade');
            $table->string('assignment_type'); // geo, user, time
            $table->string('assignment_value'); // region code, split ratio, time period
            $table->string('group'); // treatment, control
            $table->json('campaign_ids')->nullable();
            $table->timestamps();

            $table->index(['test_id', 'group']);
        });

        // Daily Test Metrics
        Schema::create('incrementality_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('incrementality_tests')->onDelete('cascade');
            $table->date('date');
            $table->string('group'); // treatment, control
            $table->string('campaign_id')->nullable();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('spend', 10, 2)->default(0);
            $table->decimal('revenue', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['test_id', 'date', 'group', 'campaign_id']);
            $table->index(['test_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incrementality_daily_metrics');
        Schema::dropIfExists('incrementality_assignments');
        Schema::dropIfExists('incrementality_tests');
    }
};
