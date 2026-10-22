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
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g., 'campaign_ai_assistant', 'new_billing_ui'
            $table->string('name'); // Human readable name
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(false);
            $table->json('conditions')->nullable(); // Advanced: { "tenant_ids": [1,2], "user_roles": ["admin"], "percentage": 50 }
            $table->json('metadata')->nullable(); // Additional config data
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
