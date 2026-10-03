<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_groups', function (Blueprint $table) {
            $table->string('assessment_score_key')->nullable()->after('score_release_policy');
            $table->dateTime('scores_synced_at')->nullable()->after('assessment_score_key');
            $table->dateTime('scores_synced_at')->nullable()->after('assessment_score_key');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->string('assessment_score_key')->nullable()->after('approval_notes');
        });

        Schema::table('skill_tests', function (Blueprint $table) {
            $table->string('assessment_score_key')->nullable()->after('score_release_policy');
        });
    }

    public function down(): void
    {
        Schema::table('skill_tests', fn (Blueprint $table) => $table->dropColumn(['assessment_score_key','scores_synced_at']));
        Schema::table('exams', fn (Blueprint $table) => $table->dropColumn('assessment_score_key'));
        Schema::table('assessment_groups', fn (Blueprint $table) => $table->dropColumn(['assessment_score_key','scores_synced_at']));
    }
};
