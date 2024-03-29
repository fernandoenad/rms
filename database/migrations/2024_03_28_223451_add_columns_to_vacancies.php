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
        Schema::table('vacancies', function (Blueprint $table) {
            $table->string('template_id')->after('status');
            $table->string('level1_status')->after('template_id');
            $table->string('level2_status')->after('level1_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropColumn('template_id');
            $table->dropColumn('level1_status');
            $table->dropColumn('level2_status');
        });
    }
};
