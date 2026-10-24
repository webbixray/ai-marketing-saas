<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The Client model (fillable/casts), DashboardController::funnel() and
     * ClientIntelligenceAgent all read/write health_* columns on the
     * clients table, but the original clients migration never created
     * them. On Postgres this 500s /admin/dashboard; on SQLite the quoted
     * identifier silently degrades to a string literal (wrong results).
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('health_score', 5, 2)->default(0)->after('settings');
            $table->string('health_grade')->nullable()->after('health_score');
            $table->string('health_trend')->nullable()->after('health_grade');
            $table->timestamp('health_last_calculated')->nullable()->after('health_trend');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'health_score',
                'health_grade',
                'health_trend',
                'health_last_calculated',
            ]);
        });
    }
};
