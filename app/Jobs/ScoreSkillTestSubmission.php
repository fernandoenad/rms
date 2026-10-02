<?php

namespace App\Jobs;

use App\Models\SkillTestAiEvaluation;
use App\Models\SkillTestAttempt;
use App\Services\AssessmentAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ScoreSkillTestSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $attemptId) {}

    protected function docxText(string $path): string
    {
        $fullPath = Storage::disk('local')->path($path);
        $zip = new ZipArchive();

        if ($zip->open($fullPath) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();

        $xml = str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml);
        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    public function handle(AssessmentAiService $ai): void
    {
        $attempt = SkillTestAttempt::with([
            'skillTest.rubricCriteria',
            'submissions'
        ])->findOrFail($this->attemptId);

        $submission = $attempt->submissions->where('is_final', true)->sortByDesc('version')->first();
        if (!$submission) {
            return;
        }

        $evaluation = SkillTestAiEvaluation::create([
            'skill_test_attempt_id' => $attempt->id,
            'status' => 'processing',
            'provider' => 'openai',
            'prompt_version' => 'v1',
            'started_at' => now(),
        ]);

        try {
            $text = trim((string) $submission->inline_response);

            if ($submission->file_path && strtolower(pathinfo($submission->original_filename, PATHINFO_EXTENSION)) === 'docx') {
                $text .= "\n\nDOCUMENT CONTENT:\n" . $this->docxText($submission->file_path);
            }

            $result = $ai->scoreSkillsSubmission($attempt->skillTest, $text);

            $evaluation->update([
                'status' => 'completed',
                'model' => $result['model'],
                'criterion_scores' => $result['criterion_scores'],
                'proposed_total' => $result['proposed_total'],
                'flags' => json_encode($result['flags']),
                'raw_response' => $result['raw_response'],
                'completed_at' => now(),
            ]);

            // Proposed score only. A human evaluator must finalize final_score.
            $attempt->update(['ai_proposed_score' => $result['proposed_total']]);
        } catch (\Throwable $e) {
            $evaluation->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}
