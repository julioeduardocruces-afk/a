<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->json('meta_user_context')->nullable()->after('customer_email')
                  ->comment('Stores user IP, UA, fbp, fbc for Meta CAPI events sent from webhooks');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn('meta_user_context');
        });
    }
};
