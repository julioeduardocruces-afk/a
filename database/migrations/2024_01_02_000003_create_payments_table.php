<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('resume_id')
                  ->constrained('resumes')
                  ->onDelete('cascade');
            $table->enum('provider', ['flow'])->default('flow');
            $table->unsignedInteger('amount')->comment('Amount in CLP cents');
            $table->string('currency', 3)->default('CLP');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->string('flow_token', 255)->nullable()->unique();
            $table->string('flow_order', 255)->nullable()->unique();
            $table->json('raw_payload_json')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'resume_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
