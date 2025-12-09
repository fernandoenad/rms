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
        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'start_date')) {
                $table->dateTime('start_date')->nullable()->after('enrollment_key');
            }
            if (!Schema::hasColumn('exams', 'end_date')) {
                $table->dateTime('end_date')->nullable()->after('start_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'start_date')) {
                $table->dropColumn('start_date');
            }
            if (Schema::hasColumn('exams', 'end_date')) {
                $table->dropColumn('end_date');
            }
        });
    }
};
