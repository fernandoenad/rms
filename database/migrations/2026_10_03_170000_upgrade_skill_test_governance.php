<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skill_tests', function (Blueprint $table) {
            $table->unsignedInteger('task_version')->default(1)->after('code');
            $table->foreignId('supersedes_skill_test_id')->nullable()->after('task_version')
                ->constrained('skill_tests')->nullOnDelete();
            $table->string('review_status', 32)->default('approved')->after('supersedes_skill_test_id');
            $table->foreignId('reviewed_by')->nullable()->after('review_status')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->string('score_release_policy', 32)->default('manual')->after('ai_scoring');
            $table->dateTime('scores_released_at')->nullable()->after('score_release_policy');
        });

        Schema::table('skill_test_rubric_criteria', function (Blueprint $table) {
            $table->unsignedInteger('criterion_version')->default(1)->after('sort_order');
            $table->foreignId('supersedes_criterion_id')->nullable()->after('criterion_version')
                ->constrained('skill_test_rubric_criteria')->nullOnDelete();
            $table->string('review_status', 32)->default('approved')->after('supersedes_criterion_id');
            $table->foreignId('reviewed_by')->nullable()->after('review_status')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->boolean('is_active')->default(true)->after('review_notes');
        });

        Schema::table('skill_test_attempts', function (Blueprint $table) {
            $table->dateTime('voided_at')->nullable()->after('evaluated_at');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');
            $table->foreignId('retake_skill_test_id')->nullable()->after('void_reason')
                ->constrained('skill_tests')->nullOnDelete();
            $table->index(['skill_test_id', 'status', 'expires_at'], 'skill_attempts_test_status_expires_idx');
            $table->index(['status', 'voided_at'], 'skill_attempts_status_voided_idx');
        });

        Schema::create('skill_test_human_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_test_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_test_rubric_criterion_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 7, 2);
            $table->text('notes')->nullable();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['skill_test_attempt_id', 'skill_test_rubric_criterion_id'],
                'skill_human_score_unique'
            );
        });

        Schema::table('assessment_incidents', function (Blueprint $table) {
            $table->foreignId('skill_test_id')->nullable()->after('exam_attempt_id')
                ->constrained('skill_tests')->cascadeOnDelete();
            $table->foreignId('skill_test_attempt_id')->nullable()->after('skill_test_id')
                ->constrained('skill_test_attempts')->cascadeOnDelete();
            $table->index(['skill_test_id', 'status'], 'assessment_incidents_skill_status_idx');
        });

        Schema::table('assessment_audit_logs', function (Blueprint $table) {
            $table->foreignId('skill_test_id')->nullable()->after('written_exam_id')
                ->constrained('skill_tests')->cascadeOnDelete();
            $table->foreignId('skill_test_rubric_criterion_id')->nullable()->after('skill_test_id')
                ->constrained('skill_test_rubric_criteria')->cascadeOnDelete();
            $table->index(['skill_test_id', 'action'], 'assessment_audit_skill_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_test_human_scores');

        Schema::table('assessment_audit_logs', function (Blueprint $table) {
            $table->dropIndex('assessment_audit_skill_action_idx');
            $table->dropConstrainedForeignId('skill_test_rubric_criterion_id');
            $table->dropConstrainedForeignId('skill_test_id');
        });

        Schema::table('assessment_incidents', function (Blueprint $table) {
            $table->dropIndex('assessment_incidents_skill_status_idx');
            $table->dropConstrainedForeignId('skill_test_attempt_id');
            $table->dropConstrainedForeignId('skill_test_id');
        });

        Schema::table('skill_test_attempts', function (Blueprint $table) {
            $table->dropIndex('skill_attempts_test_status_expires_idx');
            $table->dropIndex('skill_attempts_status_voided_idx');
            $table->dropConstrainedForeignId('retake_skill_test_id');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason']);
        });

        Schema::table('skill_test_rubric_criteria', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('supersedes_criterion_id');
            $table->dropColumn([
                'criterion_version',
                'review_status',
                'reviewed_at',
                'review_notes',
                'is_active',
            ]);
        });

        Schema::table('skill_tests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('supersedes_skill_test_id');
            $table->dropColumn([
                'task_version',
                'review_status',
                'reviewed_at',
                'review_notes',
                'score_release_policy',
                'scores_released_at',
            ]);
        });
    }
};
