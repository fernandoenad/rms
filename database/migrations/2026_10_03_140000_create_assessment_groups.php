<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->string('title');
            $table->string('code')->nullable()->index();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('assessment_group_id')
                ->nullable()
                ->after('vacancy_id')
                ->constrained('assessment_groups')
                ->nullOnDelete();
            $table->string('set_code', 50)->nullable()->after('code');
            $table->unique(['assessment_group_id', 'set_code'], 'exams_group_set_unique');
        });

        Schema::create('assessment_group_attempt_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_group_id')->constrained('assessment_groups')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('exam_attempt_id')->nullable()->constrained('exam_attempts')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['assessment_group_id', 'application_id'],
                'assessment_group_application_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_group_attempt_locks');

        Schema::table('exams', function (Blueprint $table) {
            $table->dropUnique('exams_group_set_unique');
            $table->dropConstrainedForeignId('assessment_group_id');
            $table->dropColumn('set_code');
        });

        Schema::dropIfExists('assessment_groups');
    }
};
