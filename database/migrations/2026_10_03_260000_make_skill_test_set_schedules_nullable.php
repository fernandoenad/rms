<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skill_tests', function (Blueprint $table) {
            $table->dateTime('start_date')->nullable()->change();
            $table->dateTime('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Restore valid values before making the original columns non-nullable.
        DB::table('skill_tests')
            ->whereNull('start_date')
            ->update(['start_date' => now()]);

        DB::table('skill_tests')
            ->whereNull('end_date')
            ->update(['end_date' => now()->addHour()]);

        Schema::table('skill_tests', function (Blueprint $table) {
            $table->dateTime('start_date')->nullable(false)->change();
            $table->dateTime('end_date')->nullable(false)->change();
        });
    }
};
