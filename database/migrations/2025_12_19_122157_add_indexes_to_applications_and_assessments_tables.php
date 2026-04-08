<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!$this->indexExists('applications', 'idx_applications_vacancy_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->index('vacancy_id', 'idx_applications_vacancy_id');
            });
        }
        
        if (!$this->indexExists('applications', 'idx_applications_station_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->index('station_id', 'idx_applications_station_id');
            });
        }
        
        if (!$this->indexExists('applications', 'idx_applications_last_name')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->index('last_name', 'idx_applications_last_name');
            });
        }
        
        if (!$this->indexExists('applications', 'idx_applications_application_code')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->index('application_code', 'idx_applications_application_code');
            });
        }

        if (!$this->indexExists('assessments', 'idx_assessments_application_id')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->index('application_id', 'idx_assessments_application_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists('applications', 'idx_applications_vacancy_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropIndex('idx_applications_vacancy_id');
            });
        }
        
        if ($this->indexExists('applications', 'idx_applications_station_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropIndex('idx_applications_station_id');
            });
        }
        
        if ($this->indexExists('applications', 'idx_applications_last_name')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropIndex('idx_applications_last_name');
            });
        }
        
        if ($this->indexExists('applications', 'idx_applications_application_code')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropIndex('idx_applications_application_code');
            });
        }

        if ($this->indexExists('assessments', 'idx_assessments_application_id')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->dropIndex('idx_assessments_application_id');
            });
        }
    }
};
