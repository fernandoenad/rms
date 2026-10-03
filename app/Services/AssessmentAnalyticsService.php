<?php

namespace App\Services;

use App\Models\AssessmentGroup;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;

class AssessmentAnalyticsService
{
    public function setSummary(AssessmentGroup $group): array
    {
        return Exam::query()
            ->where('assessment_group_id', $group->id)
            ->leftJoin('exam_attempts', 'exam_attempts.exam_id', '=', 'exams.id')
            ->select([
                'exams.id',
                'exams.set_code',
                'exams.title',
            ])
            ->selectRaw('COUNT(exam_attempts.id) as attempted')
            ->selectRaw('SUM(CASE WHEN exam_attempts.status = 1 THEN 1 ELSE 0 END) as in_progress')
            ->selectRaw('SUM(CASE WHEN exam_attempts.status = 2 THEN 1 ELSE 0 END) as submitted')
            ->selectRaw('AVG(CASE WHEN exam_attempts.status = 2 THEN exam_attempts.percentage END) as mean_score')
            ->selectRaw('STDDEV_POP(CASE WHEN exam_attempts.status = 2 THEN exam_attempts.percentage END) as score_sd')
            ->groupBy('exams.id', 'exams.set_code', 'exams.title')
            ->orderBy('exams.id')
            ->get()
            ->map(fn ($row) => [
                'exam_id' => (int) $row->id,
                'set_code' => $row->set_code,
                'title' => $row->title,
                'attempted' => (int) $row->attempted,
                'in_progress' => (int) $row->in_progress,
                'submitted' => (int) $row->submitted,
                'mean_score' => $row->mean_score !== null ? round((float) $row->mean_score, 2) : null,
                'score_sd' => $row->score_sd !== null ? round((float) $row->score_sd, 2) : null,
            ])
            ->all();
    }

    public function comparabilityWarnings(array $summaries): array
    {
        $means = collect($summaries)->pluck('mean_score')->filter(fn ($x) => $x !== null);
        if ($means->count() < 2) {
            return [];
        }

        $overallMean = (float) $means->avg();
        $warnings = [];

        foreach ($summaries as $set) {
            if ($set['mean_score'] === null) continue;

            $difference = abs($set['mean_score'] - $overallMean);
            if ($difference >= 10) {
                $warnings[] = "Set {$set['set_code']} mean differs from the group mean by "
                    . number_format($difference, 2)
                    . " percentage points. Review set equivalence before using results operationally.";
            }
        }

        return $warnings;
    }

    public function itemAnalytics(Exam $exam): array
    {
        $attemptScores = DB::table('exam_attempts')
            ->where('exam_id', $exam->id)
            ->where('status', 2)
            ->whereNotNull('percentage')
            ->pluck('percentage')
            ->map(fn ($x) => (float) $x)
            ->sort()
            ->values();

        $count = $attemptScores->count();
        $lowerCut = $count ? $attemptScores[(int) floor(max(0, ($count - 1) * 0.27))] : null;
        $upperCut = $count ? $attemptScores[(int) floor(max(0, ($count - 1) * 0.73))] : null;

        $items = $exam->writtenExams()->with('options')->where('status', 1)->get();
        $rows = [];

        foreach ($items as $item) {
            $responses = DB::table('exam_attempt_answers as a')
                ->join('exam_attempts as t', 't.id', '=', 'a.exam_attempt_id')
                ->leftJoin('written_exam_options as o', 'o.id', '=', 'a.selected_option_id')
                ->where('t.exam_id', $exam->id)
                ->where('t.status', 2)
                ->where('a.written_exam_id', $item->id)
                ->selectRaw('COUNT(*) as answered')
                ->selectRaw('SUM(CASE WHEN o.is_correct = 1 THEN 1 ELSE 0 END) as correct')
                ->first();

            $answered = (int) ($responses->answered ?? 0);
            $correct = (int) ($responses->correct ?? 0);

            $topCorrect = 0; $topTotal = 0; $bottomCorrect = 0; $bottomTotal = 0;
            if ($upperCut !== null && $lowerCut !== null) {
                $band = DB::table('exam_attempt_answers as a')
                    ->join('exam_attempts as t', 't.id', '=', 'a.exam_attempt_id')
                    ->leftJoin('written_exam_options as o', 'o.id', '=', 'a.selected_option_id')
                    ->where('t.exam_id', $exam->id)
                    ->where('t.status', 2)
                    ->where('a.written_exam_id', $item->id)
                    ->selectRaw('SUM(CASE WHEN t.percentage >= ? THEN 1 ELSE 0 END) as top_total', [$upperCut])
                    ->selectRaw('SUM(CASE WHEN t.percentage >= ? AND o.is_correct = 1 THEN 1 ELSE 0 END) as top_correct', [$upperCut])
                    ->selectRaw('SUM(CASE WHEN t.percentage <= ? THEN 1 ELSE 0 END) as bottom_total', [$lowerCut])
                    ->selectRaw('SUM(CASE WHEN t.percentage <= ? AND o.is_correct = 1 THEN 1 ELSE 0 END) as bottom_correct', [$lowerCut])
                    ->first();

                $topCorrect = (int) ($band->top_correct ?? 0);
                $topTotal = (int) ($band->top_total ?? 0);
                $bottomCorrect = (int) ($band->bottom_correct ?? 0);
                $bottomTotal = (int) ($band->bottom_total ?? 0);
            }

            $distractors = DB::table('exam_attempt_answers as a')
                ->join('exam_attempts as t', 't.id', '=', 'a.exam_attempt_id')
                ->join('written_exam_options as o', 'o.id', '=', 'a.selected_option_id')
                ->where('t.exam_id', $exam->id)
                ->where('t.status', 2)
                ->where('a.written_exam_id', $item->id)
                ->groupBy('o.id', 'o.option_text', 'o.is_correct')
                ->select('o.id', 'o.option_text', 'o.is_correct')
                ->selectRaw('COUNT(*) as responses')
                ->orderByDesc('responses')
                ->get();

            $rows[] = [
                'item' => $item,
                'answered' => $answered,
                'difficulty' => $answered ? round($correct / $answered, 3) : null,
                'discrimination' => ($topTotal && $bottomTotal)
                    ? round(($topCorrect / $topTotal) - ($bottomCorrect / $bottomTotal), 3)
                    : null,
                'distractors' => $distractors,
            ];
        }

        return $rows;
    }
}
