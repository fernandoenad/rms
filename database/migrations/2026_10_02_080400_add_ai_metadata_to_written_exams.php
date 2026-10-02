<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('written_exams', function (Blueprint $table) {
            $table->string('solo_level', 32)->nullable()->after('question');
            $table->string('difficulty', 32)->nullable()->after('solo_level');
            $table->text('competency_basis')->nullable()->after('difficulty');
            $table->text('rationale')->nullable()->after('answer_key');
            $table->boolean('ai_generated')->default(false)->after('rationale');
        });
    }

    public function down(): void
    {
        Schema::table('written_exams', function (Blueprint $table) {
            foreach (['solo_level','difficulty','competency_basis','rationale','ai_generated'] as $column) {
                if (Schema::hasColumn('written_exams', $column)) $table->dropColumn($column);
            }
        });
    }
};
