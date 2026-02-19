<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 255);
            $table->string('color', 7)->default('#0066ff');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed default categories
        $now = now();
        DB::table('blog_categories')->insert([
            ['slug' => 'cv-tips', 'name' => 'Consejos de CV', 'color' => '#28a745', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'ats', 'name' => 'Sistemas ATS', 'color' => '#0066ff', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'busqueda-empleo', 'name' => 'Busqueda de Empleo', 'color' => '#f59e0b', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'entrevistas', 'name' => 'Entrevistas', 'color' => '#8b5cf6', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'carrera', 'name' => 'Desarrollo de Carrera', 'color' => '#ec4899', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'noticias', 'name' => 'Noticias', 'color' => '#64748b', 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};
