<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dateTime('posting_start_at')->nullable()->after('status');
            $table->dateTime('posting_end_at')->nullable()->after('posting_start_at');
            $table->index(['status','posting_start_at','posting_end_at'], 'vacancies_posting_window_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropIndex('vacancies_posting_window_idx');
            $table->dropColumn(['posting_start_at','posting_end_at']);
        });
    }
};
