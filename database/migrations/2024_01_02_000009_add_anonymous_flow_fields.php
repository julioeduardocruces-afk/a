<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            // Allow anonymous uploads (no user account needed)
            $table->unsignedBigInteger('user_id')->nullable()->change();
            // Session-based ownership token for anonymous users
            $table->string('access_token', 64)->nullable()->unique()->after('user_id');
            // Customer email for delivery (no user account)
            $table->string('customer_email')->nullable()->after('target_role');
        });

        // Make user_id nullable in payments and download_tokens
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('download_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn(['access_token', 'customer_email']);
        });
    }
};
