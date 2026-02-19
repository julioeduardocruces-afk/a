<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            // Billing information
            $table->string('billing_name', 255)->nullable()->after('customer_email');
            $table->string('billing_rut', 20)->nullable()->after('billing_name');
            $table->string('billing_address', 500)->nullable()->after('billing_rut');
            $table->string('billing_city', 100)->nullable()->after('billing_address');
            $table->string('billing_phone', 30)->nullable()->after('billing_city');

            // Invoice tracking
            $table->enum('invoice_status', ['pending', 'issued', 'sent'])->default('pending')->after('billing_phone');
            $table->string('invoice_file', 500)->nullable()->after('invoice_status');
            $table->timestamp('invoice_issued_at')->nullable()->after('invoice_file');
            $table->timestamp('invoice_sent_at')->nullable()->after('invoice_issued_at');
            $table->string('invoice_number', 50)->nullable()->after('invoice_sent_at');

            // Index for filtering
            $table->index('invoice_status');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropIndex(['invoice_status']);
            $table->dropColumn([
                'billing_name',
                'billing_rut',
                'billing_address',
                'billing_city',
                'billing_phone',
                'invoice_status',
                'invoice_file',
                'invoice_issued_at',
                'invoice_sent_at',
                'invoice_number',
            ]);
        });
    }
};
