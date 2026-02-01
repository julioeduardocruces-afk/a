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
            $table->index('user_id', 'download_tokens_user_id_index');
            $table->index('resume_id', 'download_tokens_resume_id_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('refunded_at', 'payments_refunded_at_index');
            $table->index('created_at', 'payments_created_at_index');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->index('created_at', 'ai_usage_logs_created_at_index');
        });

        // Composite index for audit_logs download counting (action + created_at)
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
            $table->dropIndex('download_tokens_user_id_index');
            $table->dropIndex('download_tokens_resume_id_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_refunded_at_index');
            $table->dropIndex('payments_created_at_index');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropIndex('ai_usage_logs_created_at_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_action_created_at_index');
        });
    }
};
