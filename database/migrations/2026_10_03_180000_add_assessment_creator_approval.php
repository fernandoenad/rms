<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('vacancy_id')
                ->constrained('users')->nullOnDelete();
            $table->string('approval_status', 32)->default('pending')->after('status');
            $table->foreignId('approved_by')->nullable()->after('approval_status')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
        });

        Schema::table('skill_tests', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('vacancy_id')
                ->constrained('users')->nullOnDelete();
            $table->string('approval_status', 32)->default('pending')->after('status');
            $table->foreignId('approved_by')->nullable()->after('approval_status')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
        });

        // Grandfather existing assessments so this governance upgrade does not
        // unexpectedly block records that were already in operational use.
        DB::table('exams')->update([
            'approval_status'=>'approved',
            'approved_at'=>now(),
            'approval_notes'=>'Grandfathered as approved during assessment governance migration.',
        ]);

        DB::table('skill_tests')->update([
            'approval_status'=>'approved',
            'approved_at'=>now(),
            'approval_notes'=>'Grandfathered as approved during assessment governance migration.',
        ]);
    }

    public function down(): void
    {
        Schema::table('skill_tests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['approval_status','approved_at','approval_notes']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['approval_status','approved_at','approval_notes']);
        });
    }
};
