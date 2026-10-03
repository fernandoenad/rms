<?php

namespace App\Jobs;

use App\Models\AssessmentAiGenerationRun;
use App\Models\Exam;
use App\Models\WrittenExam;
use App\Models\WrittenExamOption;
use App\Services\AssessmentAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GenerateWrittenExamItemsBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public array $backoff = [30, 120];

    public function __construct(
        public int $runId,
        public int $batchSize
    ) {
        $this->onQueue('assessment-ai');
    }

    public function handle(AssessmentAiService $ai): void
    {
        $run = AssessmentAiGenerationRun::findOrFail($this->runId);
        $exam = Exam::with('vacancy')->findOrFail($run->exam_id);

        if ($exam->attempts()->exists()) {
            $this->markFailed($run, 'Generation stopped because attempts already exist.');
            return;
        }

        $lockKey = $exam->assessment_group_id
            ? 'assessment-ai-generation-written-group-'.$exam->assessment_group_id
            : 'assessment-ai-generation-exam-'.$exam->id;

        Cache::lock($lockKey, 600)->block(480, function () use ($run, $exam, $ai) {
            $run->refresh();

            if (!in_array($run->status, ['queued', 'processing'], true)) {
                return;
            }

            $run->update(['status' => 'processing']);

            $avoidQuestions = WrittenExam::query()
                ->whereHas('exam', function ($q) use ($exam) {
                    if ($exam->assessment_group_id) {
                        $q->where('assessment_group_id', $exam->assessment_group_id);
                    } else {
                        $q->whereKey($exam->id);
                    }
                })
                ->orderByDesc('id')
                ->limit(250)
                ->pluck('question')
                ->all();

            $contextOptions = $run->context_options ?: [];
            $contextOptions['avoid_questions'] = $avoidQuestions;

            try {
                $items = $ai->generateWrittenItems(
                    $exam->vacancy,
                    $this->batchSize,
                    $run->solo_distribution ?: [],
                    $contextOptions
                );

                // The administrator may have requested a newer generation while
                // this API call was in flight. Re-check immediately before
                // persistence so a superseded run cannot restore stale items.
                $run->refresh();
                if (!in_array($run->status, ['queued','processing'], true)) {
                    return;
                }

                $created = DB::transaction(function () use ($exam, $items) {
                    $created = 0;

                    foreach ($items as $generated) {
                        if (!isset($generated['question'], $generated['options'], $generated['correct_index'])
                            || !is_array($generated['options'])
                            || count($generated['options']) !== 4
                            || !in_array((int) $generated['correct_index'], [0,1,2,3], true)) {
                            continue;
                        }

                        $normalizedQuestion = trim((string) $generated['question']);
                        if ($normalizedQuestion === '') continue;

                        $alreadyExists = WrittenExam::where('exam_id', $exam->id)
                            ->where('question', $normalizedQuestion)
                            ->exists();

                        if ($alreadyExists) continue;

                        $letters = ['A','B','C','D'];
                        $answerKey = $letters[(int) $generated['correct_index']];

                        $item = WrittenExam::create([
                            'exam_id' => $exam->id,
                            'enrollment_key' => $exam->enrollment_key,
                            'question' => $normalizedQuestion,
                            'option_a' => $generated['options'][0],
                            'option_b' => $generated['options'][1],
                            'option_c' => $generated['options'][2],
                            'option_d' => $generated['options'][3],
                            'answer_key' => $answerKey,
                            'solo_level' => $generated['solo_level'] ?? null,
                            'difficulty' => $generated['difficulty'] ?? null,
                            'competency_basis' => $generated['competency_basis'] ?? null,
                            'rationale' => $generated['rationale'] ?? null,
                            'ai_generated' => true,
                            'review_status' => 'pending_review',
                            'status' => 1,
                        ]);

                        foreach ($generated['options'] as $index => $text) {
                            WrittenExamOption::create([
                                'written_exam_id' => $item->id,
                                'option_text' => $text,
                                'is_correct' => $index === (int) $generated['correct_index'],
                                'source_position' => $index + 1,
                            ]);
                        }

                        $created++;
                    }

                    return $created;
                });

                $run->increment('generated_count', $created);
                $run->increment('completed_batches');
                $this->refreshRunStatus($run);
            } catch (\Throwable $e) {
                $this->markFailed($run, $e->getMessage());
                throw $e;
            }
        });
    }

    protected function markFailed(AssessmentAiGenerationRun $run, string $message): void
    {
        $run->increment('failed_batches');
        $run->increment('completed_batches');
        $run->update(['last_error' => mb_substr($message, 0, 65000)]);
        $this->refreshRunStatus($run);
    }

    protected function refreshRunStatus(AssessmentAiGenerationRun $run): void
    {
        $run->refresh();

        if ((int) $run->completed_batches >= (int) $run->batch_count) {
            $status = 'completed';

            if ((int) $run->failed_batches > 0) {
                $status = 'completed_with_errors';
            } elseif ((int) $run->generated_count < (int) $run->requested_count) {
                $status = 'completed_partial';
            }

            $run->update(['status' => $status]);
        }
    }
}
