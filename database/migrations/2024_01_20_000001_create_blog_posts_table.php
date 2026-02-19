<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255)->unique();
            $table->string('title', 255);
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('featured_image', 500)->nullable();
            $table->string('category', 100)->nullable();

            // SEO Fields (like Yoast)
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->string('meta_keywords', 255)->nullable();
            $table->string('focus_keyword', 100)->nullable();

            // Status and publishing
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();

            // Author
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            // Stats
            $table->unsignedInteger('views_count')->default(0);

            // Reading time (minutes)
            $table->unsignedSmallInteger('reading_time')->default(1);

            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('published_at');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
