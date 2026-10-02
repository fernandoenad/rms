<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'code')) {
                $table->string('code')->nullable()->after('title')->index();
            }
            if (!Schema::hasColumn('exams', 'access_mode')) {
                $table->string('access_mode', 32)->default('all_taken_in')->after('duration');
            }
            if (!Schema::hasColumn('exams', 'shuffle_options')) {
                $table->boolean('shuffle_options')->default(true)->after('shuffle_items');
            }
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_attempts', 'expires_at')) {
                $table->dateTime('expires_at')->nullable()->after('started_at')->index();
            }
            if (!Schema::hasColumn('exam_attempts', 'correct_answers')) {
                $table->unsignedInteger('correct_answers')->nullable()->after('status');
            }
            if (!Schema::hasColumn('exam_attempts', 'total_items')) {
                $table->unsignedInteger('total_items')->nullable()->after('correct_answers');
            }
            if (!Schema::hasColumn('exam_attempts', 'percentage')) {
                $table->decimal('percentage', 6, 2)->nullable()->after('total_items');
            }
            if (!Schema::hasColumn('exam_attempts', 'scored_at')) {
                $table->dateTime('scored_at')->nullable()->after('percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            foreach (['expires_at','correct_answers','total_items','percentage','scored_at'] as $column) {
                if (Schema::hasColumn('exam_attempts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('exams', function (Blueprint $table) {
            foreach (['code','access_mode','shuffle_options'] as $column) {
                if (Schema::hasColumn('exams', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
