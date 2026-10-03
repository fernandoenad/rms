<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_groups', function (Blueprint $table) {
            $table->unsignedInteger('expected_sets')->default(1)->after('code');
            $table->json('blueprint')->nullable()->after('expected_sets');
            $table->unsignedInteger('blueprint_version')->default(1)->after('blueprint');
            $table->string('score_release_policy', 32)->default('manual')->after('status');
            $table->dateTime('scores_released_at')->nullable()->after('score_release_policy');
        });

        Schema::table('written_exams', function (Blueprint $table) {
            $table->unsignedInteger('item_version')->default(1)->after('ai_generated');
            $table->foreignId('supersedes_item_id')->nullable()->after('item_version')
                ->constrained('written_exams')->nullOnDelete();
            $table->string('review_status', 32)->default('approved')->after('supersedes_item_id');
            $table->foreignId('reviewed_by')->nullable()->after('review_status')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });

        // Existing AI items must not be silently treated as human-reviewed.
        DB::table('written_exams')
            ->where('ai_generated', true)
            ->update(['review_status' => 'pending_review']);

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dateTime('voided_at')->nullable()->after('scored_at');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');
            $table->foreignId('retake_exam_id')->nullable()->after('void_reason')
                ->constrained('exams')->nullOnDelete();
            $table->index(['status', 'voided_at']);
        });

        Schema::create('assessment_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('exam_attempt_id')->nullable()->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->text('notes');
            $table->string('status', 32)->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['assessment_group_id', 'status']);
        });

        Schema::create('assessment_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('written_exam_id')->nullable()->constrained('written_exams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['assessment_group_id', 'action']);
        });

        Schema::create('assessment_ai_generation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('requested_count');
            $table->unsignedInteger('generated_count')->default(0);
            $table->unsignedInteger('failed_batches')->default(0);
            $table->unsignedInteger('batch_count')->default(0);
            $table->unsignedInteger('completed_batches')->default(0);
            $table->json('solo_distribution');
            $table->json('context_options')->nullable();
            $table->string('status', 32)->default('queued');
            $table->text('last_error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['exam_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_ai_generation_runs');
        Schema::dropIfExists('assessment_audit_logs');
        Schema::dropIfExists('assessment_incidents');

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex(['status', 'voided_at']);
            $table->dropConstrainedForeignId('retake_exam_id');
            $table->dropColumn(['voided_at', 'void_reason']);
            $table->dropConstrainedForeignId('voided_by');
        });

        Schema::table('written_exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('supersedes_item_id');
            $table->dropColumn(['item_version', 'review_status', 'reviewed_at', 'review_notes']);
        });

        Schema::table('assessment_groups', function (Blueprint $table) {
            $table->dropColumn([
                'expected_sets',
                'blueprint',
                'blueprint_version',
                'score_release_policy',
                'scores_released_at',
            ]);
        });
    }
};
