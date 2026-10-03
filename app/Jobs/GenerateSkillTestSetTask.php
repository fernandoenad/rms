<?php

namespace App\Jobs;

use App\Models\SkillTest;
use App\Services\AssessmentAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GenerateSkillTestSetTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;
    public array $backoff = [30,120];

    public function __construct(
        public int $skillTestId,
        public array $contextOptions = [],
        public ?int $sharedRubricSourceId = null
    ) {
        $this->onQueue('assessment-ai');
    }

    public function handle(AssessmentAiService $ai): void
    {
        $test = SkillTest::with(['vacancy','skillTestGroup'])->findOrFail($this->skillTestId);

        if ((int)$test->status === 1 || $test->attempts()->whereNotNull('started_at')->exists()) {
            $test->update(['review_notes'=>'AI generation skipped because this set is already published/administered.']);
            return;
        }

        $options = $this->contextOptions;

        if ($test->skill_test_group_id) {
            $options['avoid_tasks'] = SkillTest::where('skill_test_group_id',$test->skill_test_group_id)
                ->whereKeyNot($test->id)
                ->whereNotNull('instructions')
                ->where('instructions','not like','Draft placeholder%')
                ->orderBy('set_code')
                ->get(['title','instructions','expected_output'])
                ->map(fn($row)=>[
                    'title'=>$row->title,
                    'instructions'=>$row->instructions,
                    'expected_output'=>$row->expected_output,
                ])->all();
        }

        if ($this->sharedRubricSourceId) {
            $source = SkillTest::with('rubricCriteria')->findOrFail($this->sharedRubricSourceId);
            $rubric = $source->rubricCriteria->map(fn($criterion)=>[
                'criterion'=>$criterion->criterion,
                'description'=>$criterion->description,
                'max_points'=>(float)$criterion->max_points,
            ])->values()->all();

            if (!$rubric) {
                throw new \RuntimeException('The shared-rubric source set has no generated rubric yet.');
            }

            $options['shared_rubric'] = $rubric;
        }

        $test->update([
            'review_status'=>'pending_review',
            'review_notes'=>'AI generation processing...',
            'ai_context'=>$options['additional_context'] ?? null,
            'ai_generation_focus'=>$options['generation_focus'] ?? 'mixed',
            'ai_use_qualifications'=>(bool)($options['use_qualifications'] ?? true),
            'ai_use_job_description'=>(bool)($options['use_job_description'] ?? true),
        ]);

        $payload = $ai->generateSkillsTask(
            $test->vacancy,
            (int)$test->duration,
            $options
        );

        DB::transaction(function () use ($test,$payload,$options) {
            $test->allRubricCriteria()->where('is_active',true)->update(['is_active'=>false]);

            $test->update([
                'title'=>$payload['title'],
                'instructions'=>$payload['instructions'],
                'expected_output'=>$payload['expected_output'] ?? null,
                'review_status'=>'pending_review',
                'reviewed_by'=>null,
                'reviewed_at'=>null,
                'review_notes'=>'AI-generated equivalent task requires human review before publication.',
            ]);

            $rubric = $options['shared_rubric'] ?? ($payload['rubric'] ?? []);

            foreach ($rubric as $i=>$criterion) {
                $test->allRubricCriteria()->create([
                    'criterion'=>$criterion['criterion'],
                    'description'=>$criterion['description'] ?? null,
                    'max_points'=>$criterion['max_points'],
                    'sort_order'=>$i,
                    'criterion_version'=>1,
                    'review_status'=>'pending_review',
                    'reviewed_by'=>null,
                    'reviewed_at'=>null,
                    'review_notes'=>'AI-generated/copied shared rubric criterion requires human review.',
                    'is_active'=>true,
                ]);
            }
        });
    }

    public function failed(\Throwable $e): void
    {
        SkillTest::whereKey($this->skillTestId)->update([
            'review_status'=>'pending_review',
            'review_notes'=>'AI generation failed: '.mb_substr($e->getMessage(),0,1000),
        ]);
    }
}
