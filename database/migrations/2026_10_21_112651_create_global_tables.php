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
        // Multi-Region Tables
        Schema::create('region_migrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('from_region');
            $table->string('to_region');
            $table->string('status')->default('scheduled'); // scheduled, running, completed, failed
            $table->unsignedInteger('progress')->default(0);
            $table->timestamp('estimated_completion')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('dr_drills', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->default('full'); // full, partial, tabletop
            $table->json('regions')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, approved, running, completed, failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('results')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        // Analytics Tables
        Schema::create('analytics_events_queue', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_name');
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->json('properties')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('timestamp');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'timestamp']);
            $table->index(['event_name', 'timestamp']);
            $table->index(['synced_at']);
        });

        Schema::create('analytics_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('widgets')->nullable();
            $table->json('layout')->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['tenant_id', 'is_default']);
        });

        Schema::create('analytics_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('format'); // csv, json, xlsx, pdf
            $table->json('metrics')->nullable();
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('data_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_id')->nullable();
            $table->string('action'); // read, write, delete, export
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['asset_id', 'created_at']);
        });

        // Data Governance Tables
        Schema::create('data_catalog', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // table, view, file, api, stream
            $table->string('source');
            $table->json('schema')->nullable();
            $table->string('classification')->default('internal'); // public, internal, confidential, restricted
            $table->foreignId('owner')->constrained('users')->onDelete('cascade');
            $table->foreignId('steward')->nullable()->constrained('users')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->json('lineage')->nullable();
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->timestamp('last_scanned')->nullable();
            $table->timestamp('last_accessed')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->json('retention_policy')->nullable();
            $table->json('pii_fields')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'classification']);
            $table->index(['tenant_id', 'type']);
            $table->index(['owner']);
        });

        Schema::create('data_quality_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('asset_id')->nullable()->constrained('data_catalog')->nullOnDelete();
            $table->string('type'); // completeness, uniqueness, validity, accuracy, consistency, timeliness
            $table->string('dimension');
            $table->json('condition')->nullable();
            $table->decimal('threshold', 5, 4)->default(0.95);
            $table->string('severity')->default('warning'); // info, warning, critical
            $table->boolean('enabled')->default(true);
            $table->string('schedule')->default('daily'); // hourly, daily, weekly, manual
            $table->timestamp('last_run')->nullable();
            $table->json('last_result')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['tenant_id', 'enabled']);
            $table->index(['asset_id', 'enabled']);
        });

        Schema::create('data_masking_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('asset_id')->constrained('data_catalog')->onDelete('cascade');
            $table->json('rules');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['tenant_id', 'asset_id']);
        });

        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('asset_types')->nullable();
            $table->json('classification')->nullable();
            $table->unsignedInteger('retention_days')->default(2555);
            $table->string('action')->default('delete'); // delete, archive, anonymize
            $table->string('archive_location')->nullable();
            $table->boolean('legal_hold_exempt')->default(false);
            $table->boolean('enabled')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['tenant_id', 'enabled']);
        });

        Schema::create('data_subject_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('type'); // access, rectification, erasure, portability, restriction, objection
            $table->string('email');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending'); // pending, processing, completed, rejected
            $table->json('requested_data')->nullable();
            $table->json('response_data')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['email', 'type']);
        });

        // Disaster Recovery Tables
        Schema::create('dr_backup_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('databases')->nullable();
            $table->json('tables')->nullable();
            $table->json('exclude_tables')->nullable();
            $table->string('storage');
            $table->json('retention')->nullable();
            $table->boolean('compression')->default(true);
            $table->boolean('encryption')->default(true);
            $table->string('schedule');
            $table->boolean('verify')->default(true);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run')->nullable();
            $table->string('last_status')->nullable();
            $table->timestamp('next_run')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['tenant_id', 'enabled']);
        });

        Schema::create('dr_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('dr_backup_plans')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('type')->default('scheduled'); // scheduled, manual, verification
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('tables_count')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('checksum_verified')->default(false);
            $table->boolean('restore_tested')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['plan_id', 'status']);
            $table->index(['started_at']);
        });

        Schema::create('dr_restores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_id')->constrained('dr_backups')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('type')->default('full'); // full, point_in_time, table_level
            $table->string('target_database');
            $table->timestamp('point_in_time')->nullable();
            $table->json('tables')->nullable();
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('progress')->default(0);
            $table->text('error')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('dr_failovers', function (Blueprint $table) {
            $table->id();
            $table->string('primary_region');
            $table->string('backup_region');
            $table->text('reason');
            $table->foreignId('triggered_by')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('initiated'); // initiated, running, completed, failed
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('steps')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });

        // Cost Optimization Tables
        Schema::create('cost_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('period')->default('monthly'); // daily, weekly, monthly, quarterly, yearly
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->json('filters')->nullable();
            $table->json('alert_thresholds')->nullable();
            $table->json('alert_channels')->nullable();
            $table->boolean('enabled')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['tenant_id', 'enabled']);
        });

        Schema::create('cost_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('period');
            $table->json('data');
            $table->string('format')->default('json');
            $table->timestamps();

            $table->index(['tenant_id', 'period']);
        });

        // Enterprise SSO Tables
        Schema::create('enterprise_sso_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->json('ldap')->nullable();
            $table->json('active_directory')->nullable();
            $table->json('kerberos')->nullable();
            $table->json('saml')->nullable();
            $table->boolean('auto_provision')->default(true);
            $table->string('default_role')->default('user');
            $table->boolean('sync_groups')->default(true);
            $table->unsignedInteger('sync_interval')->default(3600);
            $table->boolean('just_in_time_provisioning')->default(true);
            $table->boolean('fallback_to_local')->default(true);
            $table->timestamps();

            $table->unique('tenant_id');
        });

        // CDN Tables
        Schema::create('cdn_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('provider')->default('cloudflare');
            $table->json('config')->nullable();
            $table->json('cache_rules')->nullable();
            $table->json('waf_rules')->nullable();
            $table->json('rate_limits')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique('tenant_id');
        });

        Schema::create('cdn_purge_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->json('urls');
            $table->string('provider');
            $table->string('status'); // success, partial, failed
            $table->json('results')->nullable();
            $table->foreignId('initiated_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cdn_purge_logs');
        Schema::dropIfExists('cdn_configurations');
        Schema::dropIfExists('enterprise_sso_config');
        Schema::dropIfExists('cost_reports');
        Schema::dropIfExists('cost_budgets');
        Schema::dropIfExists('dr_failovers');
        Schema::dropIfExists('dr_restores');
        Schema::dropIfExists('dr_backups');
        Schema::dropIfExists('dr_backup_plans');
        Schema::dropIfExists('dr_drills');
        Schema::dropIfExists('data_subject_requests');
        Schema::dropIfExists('data_retention_policies');
        Schema::dropIfExists('data_masking_rules');
        Schema::dropIfExists('data_quality_rules');
        Schema::dropIfExists('data_catalog');
        Schema::dropIfExists('data_access_logs');
        Schema::dropIfExists('analytics_exports');
        Schema::dropIfExists('analytics_dashboards');
        Schema::dropIfExists('analytics_events_queue');
        Schema::dropIfExists('region_migrations');
    }
};
