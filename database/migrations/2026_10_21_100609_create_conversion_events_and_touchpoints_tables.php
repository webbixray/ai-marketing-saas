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
        // Conversion Events
        Schema::create('conversion_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('creative_id')->nullable()->constrained('creative_assets')->nullOnDelete();
            $table->string('user_identifier'); // Hashed email, cookie ID, etc.
            $table->string('conversion_type'); // purchase, lead, signup, etc.
            $table->decimal('conversion_value', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('converted_at');
            $table->string('status')->default('completed'); // completed, pending, rejected
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'client_id', 'converted_at']);
            $table->index(['tenant_id', 'campaign_id', 'converted_at']);
            $table->index(['user_identifier', 'converted_at']);
        });

        // Touchpoints
        Schema::create('touchpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversion_event_id')->nullable()->constrained('conversion_events')->nullOnDelete();
            $table->string('channel'); // meta, google, email, direct, organic, referral, etc.
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('creative_id')->nullable()->constrained('creative_assets')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->decimal('cost', 10, 2)->nullable();
            $table->json('metadata')->nullable(); // click_id, placement, device, etc.
            $table->timestamps();

            $table->index(['tenant_id', 'client_id', 'occurred_at']);
            $table->index(['tenant_id', 'conversion_event_id']);
            $table->index(['tenant_id', 'channel', 'occurred_at']);
            $table->index(['campaign_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('touchpoints');
        Schema::dropIfExists('conversion_events');
    }
};
