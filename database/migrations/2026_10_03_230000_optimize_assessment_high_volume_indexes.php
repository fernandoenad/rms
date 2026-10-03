<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->index('email', 'applications_email_idx');
            $table->index(['vacancy_id','email'], 'applications_vacancy_email_idx');
            $table->index(['vacancy_id','application_code'], 'applications_vacancy_code_idx');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->index('application_id', 'assessments_application_idx');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->index(['vacancy_id','status','start_date'], 'exams_vac_status_start_idx');
            $table->index(['assessment_group_id','status','start_date'], 'exams_group_status_start_idx');
        });

        Schema::table('written_exams', function (Blueprint $table) {
            $table->index(['exam_id','status','review_status'], 'written_exam_status_review_idx');
            $table->index(['status','review_status'], 'written_status_review_idx');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->index(['status','expires_at'], 'exam_attempts_status_expires_idx');
            $table->index(['status','started_at'], 'exam_attempts_status_started_idx');
        });

        Schema::table('exam_attempt_answers', function (Blueprint $table) {
            $table->index(['updated_at','exam_attempt_id'], 'exam_answers_updated_attempt_idx');
        });

        Schema::table('assessment_group_attempt_locks', function (Blueprint $table) {
            $table->index(['application_id','assessment_group_id'], 'group_locks_application_group_idx');
        });

        Schema::table('skill_tests', function (Blueprint $table) {
            $table->index(['vacancy_id','status','start_date'], 'skill_tests_vac_status_start_idx');
            $table->index(['status','archived_at'], 'skill_tests_status_archive_idx');
        });

        Schema::table('skill_test_attempts', function (Blueprint $table) {
            $table->index(['status','expires_at'], 'skill_attempts_status_expires_global_idx');
            $table->index(['status','final_score'], 'skill_attempts_status_final_idx');
            $table->index(['status','started_at'], 'skill_attempts_status_started_idx');
        });

        Schema::table('skill_test_submissions', function (Blueprint $table) {
            $table->index(['skill_test_attempt_id','version'], 'skill_submissions_attempt_version_idx');
        });

        Schema::table('skill_test_ai_evaluations', function (Blueprint $table) {
            $table->index(['status','created_at'], 'skill_ai_status_created_idx');
            $table->index(['skill_test_attempt_id','status','id'], 'skill_ai_attempt_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('skill_test_ai_evaluations', function (Blueprint $table) {
            $table->dropIndex('skill_ai_status_created_idx');
            $table->dropIndex('skill_ai_attempt_status_idx');
        });

        Schema::table('skill_test_submissions', function (Blueprint $table) {
            $table->dropIndex('skill_submissions_attempt_version_idx');
        });

        Schema::table('skill_test_attempts', function (Blueprint $table) {
            $table->dropIndex('skill_attempts_status_expires_global_idx');
            $table->dropIndex('skill_attempts_status_final_idx');
            $table->dropIndex('skill_attempts_status_started_idx');
        });

        Schema::table('skill_tests', function (Blueprint $table) {
            $table->dropIndex('skill_tests_vac_status_start_idx');
            $table->dropIndex('skill_tests_status_archive_idx');
        });

        Schema::table('assessment_group_attempt_locks', function (Blueprint $table) {
            $table->dropIndex('group_locks_application_group_idx');
        });

        Schema::table('exam_attempt_answers', function (Blueprint $table) {
            $table->dropIndex('exam_answers_updated_attempt_idx');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex('exam_attempts_status_expires_idx');
            $table->dropIndex('exam_attempts_status_started_idx');
        });

        Schema::table('written_exams', function (Blueprint $table) {
            $table->dropIndex('written_exam_status_review_idx');
            $table->dropIndex('written_status_review_idx');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex('exams_vac_status_start_idx');
            $table->dropIndex('exams_group_status_start_idx');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex('assessments_application_idx');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('applications_email_idx');
            $table->dropIndex('applications_vacancy_email_idx');
            $table->dropIndex('applications_vacancy_code_idx');
        });
    }
};
