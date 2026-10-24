<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * AI generation log: one row per POST /api/v1/ai/generate call, recording
     * prompt, output, provider/model, token counts, and outcome. Tenant-
     * scoped via the AiGeneration model's TenantScope (see spec §3).
     */
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // ad_copy|email|social_caption|blog|landing_page
            $table->text('brief');
            $table->string('tone')->nullable();
            $table->string('platform')->nullable();
            $table->string('model');
            $table->string('provider');
            $table->text('prompt');
            $table->text('output')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->string('status')->default('pending'); // pending|completed|failed
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
