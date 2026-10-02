<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('written_exam_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('written_exam_id')->constrained('written_exams')->cascadeOnDelete();
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('source_position')->nullable();
            $table->timestamps();
            $table->index(['written_exam_id', 'is_correct']);
        });

        Schema::create('exam_attempt_item_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('written_exam_id')->constrained('written_exams')->cascadeOnDelete();
            $table->json('option_order');
            $table->timestamps();
            $table->unique(['exam_attempt_id', 'written_exam_id'], 'attempt_item_order_unique');
        });

        Schema::table('exam_attempt_answers', function (Blueprint $table) {
            $table->foreignId('selected_option_id')->nullable()->after('written_exam_id')
                ->constrained('written_exam_options')->nullOnDelete();
        });

        DB::table('written_exams')->orderBy('id')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                $answer = strtoupper((string) $item->answer_key);
                foreach (['A','B','C','D'] as $position) {
                    $column = 'option_' . strtolower($position);
                    if ($item->{$column} === null) {
                        continue;
                    }
                    DB::table('written_exam_options')->insert([
                        'written_exam_id' => $item->id,
                        'option_text' => $item->{$column},
                        'is_correct' => $answer === $position,
                        'source_position' => ord($position) - 64,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempt_answers', function (Blueprint $table) {
            if (Schema::hasColumn('exam_attempt_answers', 'selected_option_id')) {
                $table->dropConstrainedForeignId('selected_option_id');
            }
        });
        Schema::dropIfExists('exam_attempt_item_orders');
        Schema::dropIfExists('written_exam_options');
    }
};
