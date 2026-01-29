<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes identified during audit #20.
 *
 * payments(resume_id, status) — covers 4 query patterns that filter by
 * resume_id alone. The existing (user_id, resume_id) composite cannot
 * service these due to the leftmost-prefix rule. Without this index
 * every payment lookup inside lockForUpdate scans the full table,
 * creating a concurrency bottleneck under high traffic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['resume_id', 'status'], 'payments_resume_id_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_resume_id_status_index');
        });
    }
};
