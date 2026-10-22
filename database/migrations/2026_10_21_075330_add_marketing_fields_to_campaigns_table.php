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
        Schema::table('campaigns', function (Blueprint $table) {
            // Platform & Channel
            $table->enum('platform', ['meta', 'google', 'linkedin', 'twitter', 'tiktok', 'youtube', 'email', 'sms', 'push', 'programmatic', 'other'])->nullable()->after('type');
            $table->json('channels')->nullable()->after('platform'); // e.g., ['facebook', 'instagram', 'stories', 'reels']

            // Targeting & Audience
            $table->json('audience_targeting')->nullable()->after('channels'); // demographics, interests, behaviors
            $table->json('geo_targeting')->nullable()->after('audience_targeting'); // countries, regions, cities
            $table->json('device_targeting')->nullable()->after('geo_targeting'); // mobile, desktop, tablet
            $table->json('placement_targeting')->nullable()->after('device_targeting'); // feed, stories, search, display network

            // Creative & Content
            $table->json('creatives')->nullable()->after('placement_targeting'); // ad creatives, copy, media
            $table->json('ad_copy_variants')->nullable()->after('creatives'); // A/B test variants
            $table->string('landing_page_url')->nullable()->after('ad_copy_variants');
            $table->json('utm_parameters')->nullable()->after('landing_page_url');

            // Bidding & Budget
            $table->enum('bidding_strategy', ['cpc', 'cpm', 'cpa', 'roas', 'maximize_clicks', 'maximize_conversions', 'target_cpa', 'target_roas'])->nullable()->after('budget');
            $table->decimal('bid_amount', 10, 2)->nullable()->after('bidding_strategy');
            $table->decimal('daily_budget', 10, 2)->nullable()->after('bid_amount');
            $table->decimal('lifetime_budget', 10, 2)->nullable()->after('daily_budget');

            // Performance Metrics (cached for reporting)
            $table->unsignedBigInteger('impressions')->default(0)->after('spent');
            $table->unsignedBigInteger('clicks')->default(0)->after('impressions');
            $table->unsignedBigInteger('conversions')->default(0)->after('clicks');
            $table->decimal('ctr', 5, 2)->default(0)->after('conversions'); // Click-through rate %
            $table->decimal('cpc', 10, 2)->default(0)->after('ctr'); // Cost per click
            $table->decimal('cpm', 10, 2)->default(0)->after('cpc'); // Cost per mille
            $table->decimal('cpa', 10, 2)->default(0)->after('cpm'); // Cost per acquisition
            $table->decimal('roas', 5, 2)->default(0)->after('cpa'); // Return on ad spend
            $table->decimal('conversion_rate', 5, 2)->default(0)->after('roas'); // Conversion rate %

            // Scheduling & Automation
            $table->json('schedule')->nullable()->after('end_date'); // dayparting, scheduling rules
            $table->json('automation_rules')->nullable()->after('schedule'); // automated rules for pausing/scaling
            $table->boolean('is_evergreen')->default(false)->after('automation_rules');

            // Client & Team
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete()->after('tenant_id');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->after('client_id');
            $table->json('collaborators')->nullable()->after('assigned_to'); // additional team members

            // External Integration
            $table->string('external_id')->nullable()->unique()->after('collaborators'); // ID from ad platform
            $table->string('external_account_id')->nullable()->after('external_id'); // Ad account ID
            $table->timestamp('last_synced_at')->nullable()->after('external_account_id');

            // Status & Approval
            $table->enum('approval_status', ['draft', 'pending_review', 'approved', 'rejected', 'changes_requested'])->default('draft')->after('status');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');

            // Tags & Categorization
            $table->json('tags')->nullable()->after('rejection_reason');
            $table->string('campaign_group')->nullable()->after('tags'); // for grouping related campaigns

            // Additional indexes for common queries
            $table->index(['tenant_id', 'platform']);
            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'approval_status']);
            $table->index(['tenant_id', 'external_id']);
            $table->index(['assigned_to', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // SQLite doesn't support dropping columns with indexes
        // In production (PostgreSQL), this would work fine
        if (config('database.default') === 'sqlite') {
            return;
        }

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'platform']);
            $table->dropIndex(['tenant_id', 'client_id']);
            $table->dropIndex(['tenant_id', 'approval_status']);
            $table->dropIndex(['tenant_id', 'external_id']);
            $table->dropIndex(['assigned_to', 'status']);
            
            $table->dropColumn([
                'platform', 'channels', 'audience_targeting', 'geo_targeting', 'device_targeting',
                'placement_targeting', 'creatives', 'ad_copy_variants', 'landing_page_url', 'utm_parameters',
                'bidding_strategy', 'bid_amount', 'daily_budget', 'lifetime_budget',
                'impressions', 'clicks', 'conversions', 'ctr', 'cpc', 'cpm', 'cpa', 'roas', 'conversion_rate',
                'schedule', 'automation_rules', 'is_evergreen',
                'client_id', 'assigned_to', 'collaborators',
                'external_id', 'external_account_id', 'last_synced_at',
                'approval_status', 'approved_by', 'approved_at', 'rejection_reason',
                'tags', 'campaign_group',
            ]);
        });
    }
};
