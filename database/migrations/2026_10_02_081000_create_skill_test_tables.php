<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('code')->nullable()->index();
            $table->longText('instructions');
            $table->text('expected_output')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->unsignedInteger('duration');
            $table->string('access_mode', 32)->default('all_taken_in');
            $table->json('submission_modes');
            $table->json('allowed_extensions')->nullable();
            $table->unsignedInteger('max_file_size_kb')->default(10240);
            $table->boolean('ai_scoring')->default(true);
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamps();
        });

        Schema::create('skill_test_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['skill_test_id','application_id']);
        });

        Schema::create('skill_test_rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_test_id')->constrained()->cascadeOnDelete();
            $table->string('criterion');
            $table->text('description')->nullable();
            $table->decimal('max_points', 7, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('skill_test_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('expires_at')->nullable()->index();
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedTinyInteger('status')->default(0); // 0 not started, 1 progress, 2 submitted
            $table->decimal('ai_proposed_score', 7, 2)->nullable();
            $table->decimal('final_score', 7, 2)->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('evaluated_at')->nullable();
            $table->timestamps();
            $table->unique(['skill_test_id','application_id']);
        });

        Schema::create('skill_test_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_test_attempt_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->longText('inline_response')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_final')->default(false);
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['skill_test_attempt_id','is_final']);
        });

        Schema::create('skill_test_ai_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_test_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('provider')->default('openai');
            $table->string('model')->nullable();
            $table->string('prompt_version')->default('v1');
            $table->json('criterion_scores')->nullable();
            $table->decimal('proposed_total', 7, 2)->nullable();
            $table->text('flags')->nullable();
            $table->longText('raw_response')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_test_ai_evaluations');
        Schema::dropIfExists('skill_test_submissions');
        Schema::dropIfExists('skill_test_attempts');
        Schema::dropIfExists('skill_test_rubric_criteria');
        Schema::dropIfExists('skill_test_assignments');
        Schema::dropIfExists('skill_tests');
    }
};
