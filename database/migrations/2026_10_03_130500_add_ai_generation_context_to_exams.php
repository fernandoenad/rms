<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->longText('ai_context')->nullable()->after('shuffle_options');
            $table->string('ai_generation_focus', 40)->nullable()->after('ai_context');
            $table->boolean('ai_use_qualifications')->default(true)->after('ai_generation_focus');
            $table->boolean('ai_use_job_description')->default(true)->after('ai_use_qualifications');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn([
                'ai_context',
                'ai_generation_focus',
                'ai_use_qualifications',
                'ai_use_job_description',
            ]);
        });
    }
};
