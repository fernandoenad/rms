<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_content_bank', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 32); // written_item | skill_task
            $table->foreignId('vacancy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('written_exam_id')->nullable()->constrained('written_exams')->nullOnDelete();
            $table->foreignId('skill_test_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->longText('content');
            $table->char('fingerprint', 64)->index();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->string('review_status', 32)->default('approved');
            $table->dateTime('retired_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['content_type','vacancy_id','retired_at'], 'assessment_bank_lookup_idx');
            $table->unique(['content_type','fingerprint'], 'assessment_bank_fingerprint_unique');
        });

        Schema::create('assessment_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('capability', 64);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id','capability']);
            $table->index(['capability','user_id']);
        });

        Schema::create('assessment_performance_samples', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 64);
            $table->foreignId('exam_attempt_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('skill_test_attempt_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('latency_ms');
            $table->dateTime('recorded_at');
            $table->timestamps();
            $table->index(['operation','recorded_at'], 'assessment_perf_operation_time_idx');
            $table->index('recorded_at', 'assessment_perf_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_performance_samples');
        Schema::dropIfExists('assessment_permissions');
        Schema::dropIfExists('assessment_content_bank');
    }
};
