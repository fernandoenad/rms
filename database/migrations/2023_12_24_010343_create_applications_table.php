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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->integer('vacancy_id');
            $table->string('application_code');
            $table->string('first_name');
            $table->string('middle_name');
            $table->string('last_name');
            $table->string('sitio');
            $table->string('barangay');
            $table->string('municipality');
            $table->integer('zip');
            $table->integer('age');
            $table->string('gender');
            $table->string('civil_status');
            $table->string('religion');
            $table->string('disability');
            $table->string('ethnic_group');
            $table->string('email');
            $table->string('phone');
            $table->integer('station_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
