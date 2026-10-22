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
        // SSO Configuration
        Schema::create('tenant_sso_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->json('saml')->nullable();
            $table->json('oidc')->nullable();
            $table->json('google')->nullable();
            $table->json('microsoft')->nullable();
            $table->json('okta')->nullable();
            $table->json('domain_mapping')->nullable();
            $table->boolean('auto_provision')->default(true);
            $table->string('default_role')->default('user');
            $table->boolean('just_in_time_provisioning')->default(true);
            $table->timestamps();
            
            $table->unique('tenant_id');
        });

        // SCIM Configuration
        Schema::create('tenant_scim_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->boolean('enabled')->default(false);
            $table->string('bearer_token')->nullable();
            $table->json('schemas')->nullable();
            $table->json('attributes')->nullable();
            $table->json('filter_attributes')->nullable();
            $table->integer('page_size')->default(100);
            $table->string('sort_order')->default('asc');
            $table->timestamps();
            
            $table->unique('tenant_id');
        });

        // White Label Configuration
        Schema::create('tenant_white_label_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->boolean('enabled')->default(false);
            $table->string('custom_domain')->nullable();
            $table->boolean('domain_verified')->default(false);
            $table->string('ssl_status')->default('none');
            $table->timestamp('ssl_expires_at')->nullable();
            $table->string('theme')->default('light');
            $table->string('brand_name')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->json('colors')->nullable();
            $table->json('fonts')->nullable();
            $table->text('custom_css')->nullable();
            $table->text('custom_js')->nullable();
            $table->json('assets')->nullable();
            $table->json('email_templates')->nullable();
            $table->json('login_page')->nullable();
            $table->timestamps();
            
            $table->unique('tenant_id');
            $table->index(['custom_domain']);
        });

        // SSO Audit Logs
        Schema::create('sso_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('provider');
            $table->string('event'); // login, logout, callback, error
            $table->string('user_id')->nullable();
            $table->string('email')->nullable();
            $table->json('attributes')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'created_at']);
            $table->index(['provider', 'created_at']);
        });

        // SCIM Audit Logs
        Schema::create('scim_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('operation'); // create, read, update, delete, patch
            $table->string('resource_type'); // User, Group
            $table->string('resource_id')->nullable();
            $table->string('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
        });

        // White Label Audit Logs
        Schema::create('white_label_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('action'); // configure, domain_setup, ssl_provision, theme_change, asset_upload
            $table->json('changes')->nullable();
            $table->string('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'created_at']);
        });

        // Marketplace Tables
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('short_description')->nullable();
            $table->string('category');
            $table->string('type'); // agent, template, workflow, integration, report, creative, script, connector
            $table->string('version')->default('1.0.0');
            $table->string('price_type')->default('free'); // free, one_time, subscription, usage
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_cycle')->nullable(); // monthly, yearly
            $table->json('tags')->nullable();
            $table->json('requirements')->nullable();
            $table->json('compatibility')->nullable();
            $table->json('screenshots')->nullable();
            $table->string('demo_url')->nullable();
            $table->string('documentation_url')->nullable();
            $table->string('repository_url')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_url')->nullable();
            $table->string('license_type')->default('commercial');
            $table->text('license_text')->nullable();
            $table->json('changelog')->nullable();
            $table->string('status')->default('draft'); // draft, pending_review, approved, rejected
            $table->boolean('featured')->default(false);
            $table->boolean('verified')->default(false);
            $table->unsignedInteger('download_count')->default(0);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->decimal('revenue_total', 15, 2)->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category']);
            $table->index(['developer_id', 'status']);
            $table->index(['featured', 'status']);
        });

        Schema::create('marketplace_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('listing_id')->constrained('marketplace_listings')->onDelete('cascade');
            $table->foreignId('developer_id')->constrained('users')->onDelete('cascade');
            $table->decimal('price_paid', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_cycle')->nullable();
            $table->string('status')->default('active'); // active, cancelled, refunded, expired
            $table->timestamp('purchased_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'listing_id']);
            $table->index(['developer_id', 'status']);
        });

        Schema::create('marketplace_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('listing_id')->constrained('marketplace_listings')->onDelete('cascade');
            $table->string('status')->default('installing'); // installing, active, failed, uninstalled
            $table->json('config')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'listing_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('marketplace_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('marketplace_listings')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->json('pros')->nullable();
            $table->json('cons')->nullable();
            $table->boolean('verified_purchase')->default(false);
            $table->timestamps();

            $table->unique(['listing_id', 'user_id']);
            $table->index(['listing_id', 'created_at']);
        });

        // RBAC Extension Tables
        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->json('conditions')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->index(['tenant_id', 'expires_at']);
        });

        Schema::create('user_role_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->json('conditions');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'role_id']);
            $table->index(['expires_at']);
        });

        Schema::create('permission_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('delegatee_id')->constrained('users')->onDelete('cascade');
            $table->string('permission');
            $table->json('conditions')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->index(['delegatee_id', 'expires_at']);
            $table->index(['delegator_id', 'permission']);
        });

        Schema::create('delegation_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegation_id')->constrained('permission_delegations')->onDelete('cascade');
            $table->integer('max_depth')->default(3);
            $table->boolean('revocable')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('emergency_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('target_user_id')->constrained('users')->onDelete('cascade');
            $table->string('permission');
            $table->text('reason');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at');
            $table->string('status')->default('active'); // active, revoked, expired
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->index(['target_user_id', 'status', 'expires_at']);
            $table->index(['requester_id', 'status']);
        });

        Schema::create('access_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('reviewed_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('reviewed_at');
            $table->unsignedInteger('users_reviewed')->default(0);
            $table->unsignedInteger('findings_count')->default(0);
            $table->string('status')->default('completed');
            $table->json('findings')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'reviewed_at']);
        });

        // Marketplace Webhooks

        Schema::create('marketplace_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('url');
            $table->json('events')->nullable();
            $table->string('secret')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'active']);
        });

        Schema::create('marketplace_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('marketplace_webhooks')->onDelete('cascade');
            $table->string('event');
            $table->json('payload');
            $table->integer('status_code')->nullable();
            $table->text('response')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'created_at']);
            $table->index(['success', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_webhook_logs');
        Schema::dropIfExists('marketplace_webhooks');
        Schema::dropIfExists('marketplace_reviews');
        Schema::dropIfExists('marketplace_installations');
        Schema::dropIfExists('marketplace_purchases');
        Schema::dropIfExists('marketplace_listings');
        Schema::dropIfExists('access_reviews');
        Schema::dropIfExists('emergency_access');
        Schema::dropIfExists('delegation_metadata');
        Schema::dropIfExists('permission_delegations');
        Schema::dropIfExists('user_role_conditions');
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('white_label_audit_logs');
        Schema::dropIfExists('scim_audit_logs');
        Schema::dropIfExists('sso_audit_logs');
        Schema::dropIfExists('tenant_white_label_config');
        Schema::dropIfExists('tenant_scim_config');
        Schema::dropIfExists('tenant_sso_config');
    }
};
