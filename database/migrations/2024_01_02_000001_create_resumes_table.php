<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->string('original_filename', 255);
            $table->string('original_mime', 100);
            $table->string('original_path', 500)->comment('Storage path, never public');
            $table->longText('extracted_text')->nullable();
            $table->json('structured_json')->nullable()->comment('Parsed sections: header, experience, education, skills, certs');
            $table->string('target_industry', 255)->nullable()->comment('Rubro objetivo');
            $table->string('target_role', 255)->nullable()->comment('Cargo objetivo');
            $table->enum('status', [
                'draft',
                'processing',
                'preview_ready',
                'paid',
                'delivered',
                'failed',
            ])->default('draft');
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
