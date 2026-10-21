<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assessment_device_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            $table->unsignedBigInteger('attempt_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('session_hash', 64);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'attempt_id']);
            $table->index(['user_id', 'session_hash', 'released_at'], 'assessment_device_sessions_release_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_device_sessions');
    }
};