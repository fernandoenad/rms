<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('written_exams', function (Blueprint $table) {
            $table->boolean('scoring_excluded')->default(false)->after('status')->index();
            $table->text('scoring_exclusion_reason')->nullable()->after('scoring_excluded');
            $table->timestamp('scoring_excluded_at')->nullable()->after('scoring_exclusion_reason');
            $table->unsignedBigInteger('scoring_excluded_by')->nullable()->after('scoring_excluded_at');
        });
    }

    public function down(): void
    {
        Schema::table('written_exams', function (Blueprint $table) {
            $table->dropIndex(['scoring_excluded']);
            $table->dropColumn([
                'scoring_excluded',
                'scoring_exclusion_reason',
                'scoring_excluded_at',
                'scoring_excluded_by',
            ]);
        });
    }
};
