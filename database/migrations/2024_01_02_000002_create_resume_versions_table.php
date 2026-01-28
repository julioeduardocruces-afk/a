<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')
                  ->constrained('resumes')
                  ->onDelete('cascade');
            $table->unsignedInteger('version')->default(1);
            $table->longText('optimized_text_md');
            $table->longText('optimized_text_plain');
            $table->json('ats_keywords_json')->nullable();
            $table->json('score_json')->nullable()->comment('ATS heuristic score breakdown');
            $table->json('consistency_report_json')->nullable()->comment('IA consistency check');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['resume_id', 'version']);
            $table->index('resume_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_versions');
    }
};
