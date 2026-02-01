<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->index('customer_email', 'resumes_customer_email_index');
        });

        Schema::table('download_tokens', function (Blueprint $table) {
            $table->index('expires_at', 'download_tokens_expires_at_index');
            // user_id and resume_id already have indexes from foreignId()->constrained()
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('refunded_at', 'payments_refunded_at_index');
            $table->index('created_at', 'payments_created_at_index');
        });

        // ai_usage_logs.created_at already indexed in migration 000011
        // audit_logs.action already indexed in 000005, add composite for range queries
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['action', 'created_at'], 'audit_logs_action_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropIndex('resumes_customer_email_index');
        });

        Schema::table('download_tokens', function (Blueprint $table) {
            $table->dropIndex('download_tokens_expires_at_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_refunded_at_index');
            $table->dropIndex('payments_created_at_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_action_created_at_index');
        });
    }
};
