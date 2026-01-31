<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('failure_reason', 500)->nullable()->after('raw_payload_json');
            $table->timestamp('failure_at')->nullable()->after('failure_reason');
            $table->string('refund_reason', 500)->nullable()->after('failure_at');
            $table->unsignedBigInteger('refund_amount')->nullable()->after('refund_reason');
            $table->timestamp('refunded_at')->nullable()->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['failure_reason', 'failure_at', 'refund_reason', 'refund_amount', 'refunded_at']);
        });
    }
};
