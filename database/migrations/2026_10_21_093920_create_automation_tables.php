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
        // Automation Rules
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('triggers'); // ['campaign.created', 'campaign.performance_low']
            $table->json('trigger_filters')->nullable(); // Additional filters for triggers
            $table->json('conditions'); // Conditions that must be met
            $table->string('condition_logic')->default('AND'); // AND, OR
            $table->json('actions'); // Actions to execute
            $table->integer('priority')->default(0); // Higher = executed first
            $table->boolean('is_active')->default(true);
            $table->integer('execution_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'priority']);
        });

        // Automation Action Logs
        Schema::create('automation_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('rule_id')->constrained('automation_rules')->onDelete('cascade');
            $table->string('action_type');
            $table->json('params');
            $table->json('payload');
            $table->json('result');
            $table->timestamp('executed_at');
            $table->index(['tenant_id', 'executed_at']);
            $table->index(['rule_id', 'executed_at']);
        });

        // Optimization Insights
        Schema::create('optimization_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->json('insights');
            $table->timestamp('created_at');
            $table->index(['tenant_id', 'created_at']);
        });

        // Client Health Scores
        Schema::create('client_health_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->decimal('total_score', 5, 2);
            $table->json('factors');
            $table->string('grade')->nullable();
            $table->string('trend')->nullable(); // improving, stable, declining
            $table->timestamp('created_at');
            $table->index(['client_id', 'created_at']);
        });

        // Client Alerts
        Schema::create('client_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('type'); // churn_risk, upsell_opportunity, performance_drop, budget_pacing
            $table->string('severity'); // info, warning, high, critical
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->boolean('acknowledged')->default(false);
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'created_at']);
            $table->index(['type', 'severity']);
        });

        // Client Reports
        Schema::create('client_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('report_type'); // scheduled, on_demand, churn_risk, performance
            $table->json('data');
            $table->string('format')->default('json'); // json, pdf
            $table->string('status')->default('generated'); // generated, sent, viewed
            $table->timestamp('generated_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->index(['client_id', 'generated_at']);
        });

        // Client NPS
        Schema::create('client_nps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->integer('score'); // -100 to 100
            $table->text('feedback')->nullable();
            $table->date('survey_date');
            $table->unsignedBigInteger('survey_id')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'survey_date']);
        });

        // Client Goals
        Schema::create('client_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('metric'); // roas, cpa, conversions, spend, revenue
            $table->decimal('target_value', 15, 2);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->string('status')->default('active'); // active, achieved, missed, paused
            $table->date('target_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'status']);
        });

        // Creative Assets
        Schema::create('creative_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type'); // image, video, carousel, story, reel
            $table->string('format'); // jpg, png, mp4, gif
            $table->string('media_url');
            $table->json('elements')->nullable(); // headline, body, cta, media_url
            $table->json('performance_metrics')->nullable(); // ctr, cvr, roas, impressions, etc.
            $table->decimal('frequency', 5, 2)->default(0);
            $table->string('status')->default('active'); // active, paused, archived, testing
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['campaign_id', 'status']);
        });

        // Creative Performance Daily
        Schema::create('creative_performance_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_id')->constrained('creative_assets')->onDelete('cascade');
            $table->date('date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('spend', 10, 2)->default(0);
            $table->decimal('ctr', 5, 2)->default(0);
            $table->decimal('cvr', 5, 2)->default(0);
            $table->decimal('cpc', 10, 2)->default(0);
            $table->decimal('cpa', 10, 2)->default(0);
            $table->decimal('roas', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['creative_id', 'date']);
            $table->index(['date', 'creative_id']);
        });

        // Client Meetings
        Schema::create('client_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->date('meeting_date');
            $table->string('type'); // kickoff, review, strategy, check_in, qbr
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->json('attendees')->nullable(); // user_ids
            $table->integer('duration_minutes')->nullable();
            $table->string('status')->default('completed'); // scheduled, completed, cancelled
            $table->timestamps();
            $table->index(['client_id', 'meeting_date']);
        });

        // Client Communications
        Schema::create('client_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('direction'); // inbound, outbound
            $table->string('channel'); // email, slack, teams, phone, meeting
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who sent/received
            $table->timestamp('response_at')->nullable();
            $table->boolean('is_automated')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'created_at']);
            $table->index(['client_id', 'direction']);
        });

        // Client Portal Sessions
        Schema::create('client_portal_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->json('pages_visited')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'started_at']);
        });

        // Support Tickets (for client health)
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('open'); // open, in_progress, resolved, closed
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->string('category')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('client_portal_sessions');
        Schema::dropIfExists('client_communications');
        Schema::dropIfExists('client_meetings');
        Schema::dropIfExists('creative_performance_daily');
        Schema::dropIfExists('creative_assets');
        Schema::dropIfExists('client_goals');
        Schema::dropIfExists('client_nps');
        Schema::dropIfExists('client_reports');
        Schema::dropIfExists('client_alerts');
        Schema::dropIfExists('client_health_scores');
        Schema::dropIfExists('optimization_insights');
        Schema::dropIfExists('automation_action_logs');
        Schema::dropIfExists('automation_rules');
    }
};
