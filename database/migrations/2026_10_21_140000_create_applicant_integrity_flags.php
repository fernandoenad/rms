<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integrity_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained('vacancies')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('switch_count')->default(5);
            $table->unsignedInteger('total_hidden_seconds')->default(60);
            $table->unsignedInteger('average_hidden_seconds')->default(30);
            $table->unsignedInteger('longest_hidden_seconds')->default(120);
            $table->boolean('is_active')->default(true);
            $table->string('authority_reference');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['vacancy_id','version']);
            $table->index(['vacancy_id','is_active']);
        });
        Schema::create('applicant_integrity_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->restrictOnDelete();
            $table->foreignId('vacancy_id')->constrained('vacancies')->restrictOnDelete();
            $table->foreignId('rule_set_id')->nullable()->constrained('integrity_rule_sets')->restrictOnDelete();
            $table->string('source_type', 16); // written, skills, manual
            $table->unsignedBigInteger('source_attempt_id')->nullable();
            $table->json('signals')->nullable();
            $table->json('metrics')->nullable();
            $table->string('status', 16)->default('pending'); // pending, confirmed, cleared
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['rule_set_id','source_type','source_attempt_id'], 'integrity_flag_unique_auto_detection');
            $table->index(['vacancy_id','status','application_id'], 'integrity_flag_vacancy_status_application');
        });
        Schema::create('applicant_integrity_flag_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flag_id')->constrained('applicant_integrity_flags')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->text('reason')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['flag_id','created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('applicant_integrity_flag_actions');
        Schema::dropIfExists('applicant_integrity_flags');
        Schema::dropIfExists('integrity_rule_sets');
    }
};
