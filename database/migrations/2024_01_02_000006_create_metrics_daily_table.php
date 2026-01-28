<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('uploads')->default(0);
            $table->unsignedInteger('previews')->default(0);
            $table->unsignedInteger('paid')->default(0);
            $table->unsignedBigInteger('revenue')->default(0)->comment('CLP cents');
            $table->unsignedInteger('avg_process_time_ms')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics_daily');
    }
};
