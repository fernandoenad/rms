<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->index(
                ['exam_id', 'status', 'started_at'],
                'exam_attempts_exam_status_started_idx'
            );

            $table->index(
                ['exam_id', 'status', 'expires_at'],
                'exam_attempts_exam_status_expires_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex('exam_attempts_exam_status_started_idx');
            $table->dropIndex('exam_attempts_exam_status_expires_idx');
        });
    }
};
