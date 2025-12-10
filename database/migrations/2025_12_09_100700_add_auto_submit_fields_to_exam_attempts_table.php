<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_attempts', 'auto_submitted')) {
                $table->boolean('auto_submitted')->default(false)->after('question_order');
            }
            if (!Schema::hasColumn('exam_attempts', 'auto_submit_reason')) {
                $table->string('auto_submit_reason')->nullable()->after('auto_submitted');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('exam_attempts', 'auto_submit_reason')) {
                $table->dropColumn('auto_submit_reason');
            }
            if (Schema::hasColumn('exam_attempts', 'auto_submitted')) {
                $table->dropColumn('auto_submitted');
            }
        });
    }
};
