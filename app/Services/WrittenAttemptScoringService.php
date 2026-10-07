<?php

namespace App\Services;

use App\Models\AssessmentScoreChange;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Support\Facades\DB;

class WrittenAttemptScoringService
{
    public function __construct(
        protected AssessmentScoreSyncService $scoreSync
    ) {
    }

    public function calculate(ExamAttempt $attempt): array
    {
        $itemIds = DB::table('written_exams')
            ->where('exam_id', $attempt->exam_id)
            ->where('status', 1)
            ->where('scoring_excluded', false)
            ->pluck('id');

        $total = $itemIds->count();

        $correct = DB::table('exam_attempt_answers as a')
            ->join('written_exams as w', 'w.id', '=', 'a.written_exam_id')
            ->leftJoin('written_exam_options as o', 'o.id', '=', 'a.selected_option_id')
            ->where('a.exam_attempt_id', $attempt->id)
            ->whereIn('a.written_exam_id', $itemIds)
            ->where(function ($query) {
                $query->where('o.is_correct', 1)
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('a.selected_option_id')
                            ->whereNotNull('a.selected_option')
                            ->whereRaw('UPPER(a.selected_option) = UPPER(w.answer_key)');
                    });
            })
            ->count();

        $answered = DB::table('exam_attempt_answers')
            ->where('exam_attempt_id', $attempt->id)
            ->whereIn('written_exam_id', $itemIds)
            ->where(function ($query) {
                $query->whereNotNull('selected_option_id')
                    ->orWhereNotNull('selected_option');
            })
            ->count();

        return [
            'correct_answers' => $correct,
            'total_items' => $total,
            'percentage' => $total > 0 ? round(($correct / $total) * 100, 2) : 0,
            'answered_items' => $answered,
        ];
    }

    public function apply(ExamAttempt $attempt): array
    {
        $score = $this->calculate($attempt);

        $attempt->update([
            'correct_answers' => $score['correct_answers'],
            'total_items' => $score['total_items'],
            'percentage' => $score['percentage'],
            'scored_at' => now(),
        ]);

        return $score;
    }

    public function rescoreCompletedExam(Exam $exam, string $reason, ?int $changedBy = null): array
    {
        $rescored = 0;
        $changed = 0;

        ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('status', 2)
            ->orderBy('id')
            ->chunkById(250, function ($attempts) use ($reason, $changedBy, &$rescored, &$changed) {
                foreach ($attempts as $attempt) {
                    $previous = $attempt->percentage;
                    $score = $this->calculate($attempt);

                    $attempt->update([
                        'correct_answers' => $score['correct_answers'],
                        'total_items' => $score['total_items'],
                        'percentage' => $score['percentage'],
                        'scored_at' => now(),
                    ]);

                    $rescored++;

                    if ($previous === null || abs((float) $previous - (float) $score['percentage']) > 0.0001) {
                        AssessmentScoreChange::create([
                            'exam_attempt_id' => $attempt->id,
                            'previous_score' => $previous,
                            'new_score' => $score['percentage'],
                            'source' => 'item_scoring_exclusion',
                            'reason' => $reason,
                            'changed_by' => $changedBy,
                        ]);
                        $changed++;
                    }

                    $this->scoreSync->syncWrittenAttempt($attempt->fresh());
                }
            });

        return compact('rescored', 'changed');
    }
}
