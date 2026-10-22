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
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_token')->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_sent_at')->nullable()->after('email_verification_token');
            $table->string('password_reset_token')->nullable()->after('email_verification_sent_at');
            $table->timestamp('password_reset_sent_at')->nullable()->after('password_reset_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_verification_token',
                'email_verification_sent_at',
                'password_reset_token',
                'password_reset_sent_at',
            ]);
        });
    }
};
