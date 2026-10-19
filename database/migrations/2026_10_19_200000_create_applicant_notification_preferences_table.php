<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('applicant_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_profile_id')->unique()->constrained('applicant_profiles')->cascadeOnDelete();
            $table->boolean('email_enabled')->default(false);
            $table->boolean('email_application_updates')->default(false);
            $table->boolean('email_assessment_updates')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('applicant_notification_preferences'); }
};
