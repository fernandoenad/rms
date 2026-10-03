<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_groups', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false)->after('status');
            $table->text('pause_reason')->nullable()->after('is_paused');
            $table->dateTime('paused_at')->nullable()->after('pause_reason');
            $table->foreignId('paused_by')->nullable()->after('paused_at')->constrained('users')->nullOnDelete();
            $table->dateTime('archived_at')->nullable()->after('paused_by');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false)->after('status');
            $table->text('pause_reason')->nullable()->after('is_paused');
            $table->dateTime('paused_at')->nullable()->after('pause_reason');
            $table->foreignId('paused_by')->nullable()->after('paused_at')->constrained('users')->nullOnDelete();
            $table->dateTime('archived_at')->nullable()->after('paused_by');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('skill_tests', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false)->after('status');
            $table->text('pause_reason')->nullable()->after('is_paused');
            $table->dateTime('paused_at')->nullable()->after('pause_reason');
            $table->foreignId('paused_by')->nullable()->after('paused_at')->constrained('users')->nullOnDelete();
            $table->dateTime('archived_at')->nullable()->after('paused_by');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('assessment_time_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('skill_test_attempt_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('minutes');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['exam_attempt_id','created_at'], 'assessment_time_ext_exam_idx');
            $table->index(['skill_test_attempt_id','created_at'], 'assessment_time_ext_skill_idx');
        });

        Schema::create('assessment_score_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('skill_test_attempt_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('previous_score', 7, 2)->nullable();
            $table->decimal('new_score', 7, 2);
            $table->string('source', 40)->default('human');
            $table->text('reason');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['exam_attempt_id','created_at'], 'assessment_score_changes_exam_idx');
            $table->index(['skill_test_attempt_id','created_at'], 'assessment_score_changes_skill_idx');
        });

        Schema::create('skill_test_attempt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_test_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->dateTime('event_at');
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['skill_test_attempt_id','event_at'], 'skill_attempt_events_timeline_idx');
            $table->index(['event_type','event_at'], 'skill_attempt_events_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_test_attempt_events');
        Schema::dropIfExists('assessment_score_changes');
        Schema::dropIfExists('assessment_time_extensions');

        Schema::table('skill_tests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropConstrainedForeignId('paused_by');
            $table->dropColumn(['is_paused','pause_reason','paused_at','archived_at']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropConstrainedForeignId('paused_by');
            $table->dropColumn(['is_paused','pause_reason','paused_at','archived_at']);
        });

        Schema::table('assessment_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropConstrainedForeignId('paused_by');
            $table->dropColumn(['is_paused','pause_reason','paused_at','archived_at']);
        });
    }
};
