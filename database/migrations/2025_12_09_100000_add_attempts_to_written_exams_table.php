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
        Schema::table('written_exams', function (Blueprint $table) {
            if (!Schema::hasColumn('written_exams', 'attempts')) {
                $table->unsignedInteger('attempts')->default(0)->after('answer_key');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('written_exams', function (Blueprint $table) {
            if (Schema::hasColumn('written_exams', 'attempts')) {
                $table->dropColumn('attempts');
            }
        });
    }
};
