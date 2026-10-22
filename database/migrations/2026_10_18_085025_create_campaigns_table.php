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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'cancelled', 'archived'])->default('draft');
            $table->enum('type', ['social_media', 'email', 'search', 'display', 'video'])->nullable();
            $table->decimal('budget', 10, 2)->default(0.00);
            $table->decimal('spent', 10, 2)->default(0.00);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('targeting')->nullable(); // Store targeting criteria as JSON
            $table->json('settings')->nullable(); // Store campaign settings as JSON
            $table->timestamps();
            
            // Indexes for common queries
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
