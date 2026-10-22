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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event'); // e.g., 'user.created', 'campaign.updated', 'client.deleted'
            $table->string('auditable_type')->nullable(); // Model class name
            $table->unsignedBigInteger('auditable_id')->nullable(); // Model ID
            $table->json('old_values')->nullable(); // Previous state
            $table->json('new_values')->nullable(); // New state
            $table->json('properties')->nullable(); // Additional context (IP, user agent, etc.)
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['tenant_id', 'event']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'auditable_type', 'auditable_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
