<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The ApiKey model uses the SoftDeletes trait, but the original
     * api_keys migration never created the deleted_at column, which made
     * every query against the table fail (500 on all /api-keys endpoints).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('api_keys', 'deleted_at')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
