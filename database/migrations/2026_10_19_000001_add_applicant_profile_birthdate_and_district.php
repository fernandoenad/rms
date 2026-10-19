<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            // Older profiles do not yet have a birthdate; prompt for it on edit.
            $table->date('date_of_birth')->nullable()->after('age');
            // A reference into the existing HRMIS offices table (mysql_2).
            // No cross-database foreign key, and no change to applications.
            $table->unsignedBigInteger('district_id')->nullable()->after('municipality');
        });
    }

    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'district_id']);
        });
    }
};
