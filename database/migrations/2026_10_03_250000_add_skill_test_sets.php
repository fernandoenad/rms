<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_test_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('code')->nullable();
            $table->unsignedTinyInteger('expected_sets')->default(1);
            $table->boolean('status')->default(false);
            $table->string('score_release_policy', 32)->default('manual');
            $table->string('assessment_score_key')->nullable();
            $table->dateTime('scores_released_at')->nullable();
            $table->dateTime('scores_synced_at')->nullable();
            $table->boolean('is_paused')->default(false);
            $table->text('pause_reason')->nullable();
            $table->dateTime('paused_at')->nullable();
            $table->foreignId('paused_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vacancy_id','code'], 'skill_test_groups_vacancy_code_unique');
            $table->index(['vacancy_id','status']);
        });

        Schema::table('skill_tests', function (Blueprint $table) {
            $table->foreignId('skill_test_group_id')->nullable()->after('vacancy_id')
                ->constrained('skill_test_groups')->nullOnDelete();
            $table->string('set_code', 50)->nullable()->after('code');
            $table->index(['skill_test_group_id','set_code'], 'skill_tests_group_set_idx');
        });

        Schema::create('skill_test_group_attempt_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_test_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_test_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['skill_test_group_id','application_id'],
                'skill_group_application_unique'
            );
            $table->index(['skill_test_id','application_id'], 'skill_group_set_application_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_test_group_attempt_locks');

        Schema::table('skill_tests', function (Blueprint $table) {
            $table->dropForeign(['skill_test_group_id']);
            $table->dropIndex('skill_tests_group_set_idx');
            $table->dropColumn(['skill_test_group_id','set_code']);
        });

        Schema::dropIfExists('skill_test_groups');
    }
};
