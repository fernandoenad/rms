<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_accommodations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('skill_test_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('extra_minutes')->default(0);
            $table->boolean('large_text')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['application_id','assessment_group_id'], 'assessment_accom_group_idx');
            $table->index(['application_id','exam_id'], 'assessment_accom_exam_idx');
            $table->index(['application_id','skill_test_id'], 'assessment_accom_skill_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_accommodations');
    }
};
