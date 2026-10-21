<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assessment_nullification_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained('vacancies')->restrictOnDelete();
            $table->foreignId('written_group_id')->nullable()->constrained('assessment_groups')->restrictOnDelete();
            $table->foreignId('skills_group_id')->nullable()->constrained('skill_test_groups')->restrictOnDelete();
            $table->string('board_reference');
            $table->date('board_date');
            $table->text('reason');
            $table->string('status', 16)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('restoration_reference')->nullable();
            $table->text('restoration_reason')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();
            $table->index(['vacancy_id', 'status']);
        });

        Schema::create('assessment_nullification_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('assessment_nullification_batches')->restrictOnDelete();
            $table->foreignId('application_id')->constrained('applications')->restrictOnDelete();
            $table->string('criterion_key');
            $table->boolean('previously_present');
            $table->text('previous_value_json')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'application_id', 'criterion_key'], 'nullification_batch_application_criterion');
            $table->index(['application_id', 'criterion_key'], 'nullification_application_criterion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_nullification_items');
        Schema::dropIfExists('assessment_nullification_batches');
    }
};
