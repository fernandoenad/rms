<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('applicant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            foreach (['first_name','middle_name','last_name','sitio','barangay','municipality','gender','civil_status','religion','disability','ethnic_group','email','phone'] as $field) {
                $table->string($field)->nullable();
            }
            $table->integer('zip')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->timestamp('profile_completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('oauth_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_subject');
            $table->string('provider_email')->nullable();
            $table->boolean('email_verified')->default(false);
            $table->timestamps();
            $table->unique(['provider', 'provider_subject']);
        });

        Schema::create('applicant_application_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_profile_id')->constrained('applicant_profiles')->cascadeOnDelete();
            // Deliberately avoid altering the externally consumed applications table.
            $table->foreignId('application_id')->unique()->constrained('applications')->restrictOnDelete();
            $table->string('link_method', 32);
            $table->timestamp('verified_at');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('application_claim_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_profile_id')->constrained('applicant_profiles')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->restrictOnDelete();
            $table->string('claimed_application_code');
            $table->string('claimed_email');
            $table->string('status', 32)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->index(['status','created_at']);
        });

        Schema::create('applicant_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('applicant_profile_id')->nullable()->constrained('applicant_profiles')->nullOnDelete();
            $table->unsignedBigInteger('application_id')->nullable();
            $table->string('event_type', 80);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['applicant_audit_logs','application_claim_requests','applicant_application_links','oauth_identities','applicant_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
