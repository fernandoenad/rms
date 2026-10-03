<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('target_type', 32);
            $table->foreignId('assessment_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('skill_test_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->longText('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['target_type','event','created_at'], 'assessment_snapshot_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_snapshots');
    }
};
