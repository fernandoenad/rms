<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            if (!Schema::hasColumn('templates', 'description')) {
                $table->text('description')->nullable()->after('type');
            }
            if (!Schema::hasColumn('templates', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('description');
            }
            if (!Schema::hasColumn('templates', 'parent_template_id')) {
                $table->unsignedBigInteger('parent_template_id')->nullable()->after('version')->index();
            }
            if (!Schema::hasColumn('templates', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('status')->index();
            }
        });

        if (!Schema::hasTable('scoring_template_criteria')) {
            Schema::create('scoring_template_criteria', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
                $table->string('key');
                $table->string('label');
                $table->decimal('max_points', 8, 3);
                $table->string('source_type')->default('manual');
                $table->text('instructions')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['template_id', 'key']);
                $table->index(['template_id', 'sort_order']);
            });
        }

        // Backfill the normalized criteria table from the legacy JSON payload.
        DB::table('templates')->orderBy('id')->chunkById(100, function ($templates) {
            foreach ($templates as $template) {
                $decoded = json_decode((string) $template->template, true);
                if (!is_array($decoded)) {
                    continue;
                }

                $order = 0;
                foreach ($decoded as $key => $value) {
                    if (!is_numeric($value)) {
                        continue;
                    }

                    DB::table('scoring_template_criteria')->updateOrInsert(
                        ['template_id' => $template->id, 'key' => (string) $key],
                        [
                            'label' => str_replace('_', ' ', (string) $key),
                            'max_points' => (float) $value,
                            'source_type' => 'manual',
                            'sort_order' => $order++,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_template_criteria');

        Schema::table('templates', function (Blueprint $table) {
            foreach (['archived_at', 'parent_template_id', 'version', 'description'] as $column) {
                if (Schema::hasColumn('templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
