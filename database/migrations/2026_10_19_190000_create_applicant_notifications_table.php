<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('applicant_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_profile_id')->constrained('applicant_profiles')->cascadeOnDelete();
            $table->string('event_key', 120)->unique();
            $table->string('type', 60);
            $table->string('title', 200);
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['applicant_profile_id', 'read_at', 'created_at'], 'applicant_notification_inbox_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('applicant_notifications');
    }
};
