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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('industry')->nullable();
            $table->text('description')->nullable();
            $table->json('branding')->nullable(); // logo, colors, fonts, voice guidelines
            $table->json('social_accounts')->nullable(); // connected social media accounts
            $table->json('ad_accounts')->nullable(); // connected ad platform accounts
            $table->string('timezone')->default('UTC');
            $table->string('currency')->default('USD');
            $table->enum('status', ['active', 'onboarded', 'paused', 'archived'])->default('onboarded');
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->decimal('monthly_budget', 12, 2)->nullable();
            $table->json('billing_details')->nullable(); // invoicing address, tax ID, payment terms
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('team_members')->nullable(); // additional team members with roles
            $table->json('settings')->nullable(); // client-specific settings
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'account_manager_id']);
            $table->index(['slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
