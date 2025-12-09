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
            if (!Schema::hasColumn('written_exams', 'status')) {
                $table->unsignedTinyInteger('status')->default(1)->after('attempts');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('written_exams', function (Blueprint $table) {
            if (Schema::hasColumn('written_exams', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
