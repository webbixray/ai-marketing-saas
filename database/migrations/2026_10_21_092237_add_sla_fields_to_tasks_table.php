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
        Schema::table('tasks', function (Blueprint $table) {
            // SLA Tracking
            $table->timestamp('sla_started_at')->nullable()->after('due_date'); // When SLA clock started
            $table->timestamp('sla_due_at')->nullable()->after('sla_started_at'); // Calculated SLA deadline
            $table->timestamp('first_response_at')->nullable()->after('sla_due_at'); // First response timestamp
            $table->timestamp('resolved_at')->nullable()->after('first_response_at'); // Resolution timestamp
            $table->unsignedInteger('sla_response_minutes')->nullable()->after('resolved_at'); // Response SLA in minutes
            $table->unsignedInteger('sla_resolution_minutes')->nullable()->after('sla_response_minutes'); // Resolution SLA in minutes
            $table->boolean('sla_response_breached')->default(false)->after('sla_resolution_minutes');
            $table->boolean('sla_resolution_breached')->default(false)->after('sla_response_breached');
            $table->json('sla_pauses')->nullable()->after('sla_resolution_breached'); // Track SLA pauses
            $table->unsignedInteger('sla_paused_minutes')->default(0)->after('sla_pauses'); // Total paused time

            // Indexes for SLA queries
            $table->index(['tenant_id', 'sla_due_at']);
            $table->index(['tenant_id', 'sla_response_breached']);
            $table->index(['tenant_id', 'sla_resolution_breached']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // SQLite doesn't support dropping columns with indexes
        if (config('database.default') === 'sqlite') {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'sla_due_at']);
            $table->dropIndex(['tenant_id', 'sla_response_breached']);
            $table->dropIndex(['tenant_id', 'sla_resolution_breached']);
            
            $table->dropColumn([
                'sla_started_at',
                'sla_due_at',
                'first_response_at',
                'resolved_at',
                'sla_response_minutes',
                'sla_resolution_minutes',
                'sla_response_breached',
                'sla_resolution_breached',
                'sla_pauses',
                'sla_paused_minutes',
            ]);
        });
    }
};
