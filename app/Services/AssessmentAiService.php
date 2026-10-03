<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Vacancy;
use OpenAI;
use RuntimeException;

class AssessmentAiService
{
    protected function client()
    {
        $key = config('services.openai.key') ?: env('OPENAI_API_KEY');
        if (!$key) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }
        return OpenAI::client($key);
    }

    protected function model(): string
    {
        return config('services.openai.model')
            ?: optional(Setting::where('item', 'ai_model_id')->first())->value
            ?: 'gpt-4o-mini';
    }

    protected function vacancyContext(Vacancy $vacancy, array $options = []): string
    {
        $useQualifications = $options['use_qualifications'] ?? true;
        $useJobDescription = $options['use_job_description'] ?? true;
        $additionalContext = trim((string) ($options['additional_context'] ?? ''));
        $focus = trim((string) ($options['generation_focus'] ?? 'mixed'));

        $parts = [
            "Position: {$vacancy->position_title}",
            "Salary grade: {$vacancy->salary_grade}",
            "Generation focus: {$focus}",
        ];

        if ($useQualifications) {
            $parts[] = "Qualification standards / qualifications:\n"
                . ($vacancy->qualifications ?: 'Not provided');
        }

        if ($useJobDescription) {
            $parts[] = "Job description / vacancy details:\n"
                . ($vacancy->vacancy ?: 'Not provided');
        }

        if ($additionalContext !== '') {
            $parts[] = "Additional administrator-provided assessment context:\n"
                . $additionalContext;
        }

        return implode("\n\n", $parts);
    }

    protected function decodeJson(string $text): array
    {
        $text = trim($text);
        $first = strpos($text, '{');
        $last = strrpos($text, '}');
        if ($first !== false && $last !== false && $last >= $first) {
            $text = substr($text, $first, $last - $first + 1);
        }

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('AI returned invalid JSON.');
        }
        return $decoded;
    }

    public function generateWrittenItems(
        Vacancy $vacancy,
        int $count,
        array $soloDistribution,
        array $contextOptions = []
    ): array
    {
        $system = <<<'PROMPT'
You are an expert employment-assessment item writer. Generate defensible single-best-answer multiple-choice items aligned to the supplied qualification standards and job description.

HARD RULES:
1. Follow the SOLO taxonomy as the abstraction/cognitive framework. Each item must be tagged internally as unistructural, multistructural, relational, or extended_abstract according to the thinking genuinely required.
2. Each displayed item must read naturally as a contextual stem followed by a question, with NO visible labels such as "Stem:", "Question:", or SOLO labels.
3. Provide exactly four plausible options.
4. Options must have parallel grammar and broadly similar text length. Avoid making the correct answer conspicuously longer.
5. Exactly one option must be clearly best.
6. Avoid all/none-of-the-above, trivial clues, duplicated wording that reveals the answer, unnecessary negatives, and irrelevant trivia.
7. Favor application, judgment, and job-relevant scenarios over pure recall when supported by the job.
8. Return a concise rationale and the job/qualification basis for internal reviewer use.
9. Do not mention that the item was AI-generated.
10. Output VALID JSON ONLY, no markdown.

JSON shape:
{"items":[{"question":"...","options":["...","...","...","..."],"correct_index":0,"solo_level":"relational","difficulty":"moderate","competency_basis":"...","rationale":"..."}]}
PROMPT;

        $user = $this->vacancyContext($vacancy, $contextOptions)
            . "\n\nUse only the supplied context as the substantive basis for job-specific content. "
            . "If the context is insufficient for a defensible item, write a broader job-relevant item rather than inventing a policy, procedure, duty, threshold, or factual requirement."
            . "\n\nGenerate exactly {$count} items."
            . "\nTarget SOLO distribution: " . json_encode($soloDistribution)
            . "\nEnsure the full set broadly follows the requested distribution.";

        $response = $this->client()->chat()->create([
            'model' => $this->model(),
            'temperature' => 0.4,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ]);

        $decoded = $this->decodeJson($response['choices'][0]['message']['content'] ?? '');
        if (!isset($decoded['items']) || !is_array($decoded['items'])) {
            throw new RuntimeException('AI returned an invalid written-exam payload.');
        }

        return array_slice($decoded['items'], 0, $count);
    }

    public function generateSkillsTask(Vacancy $vacancy, int $durationMinutes): array
    {
        $system = <<<'PROMPT'
You design authentic employment skills tests. Use the supplied qualification standards and job description. Create one job-relevant performance task that can be completed on a mobile phone where practical, with clear instructions, expected output, and an analytic rubric totaling 100 points. Avoid trivia.

Output VALID JSON ONLY:
{"title":"...","instructions":"...","expected_output":"...","rubric":[{"criterion":"...","description":"...","max_points":40}]}
PROMPT;

        $response = $this->client()->chat()->create([
            'model' => $this->model(),
            'temperature' => 0.4,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $this->vacancyContext($vacancy) . "\nDuration: {$durationMinutes} minutes."],
            ],
        ]);

        $decoded = $this->decodeJson($response['choices'][0]['message']['content'] ?? '');
        if (empty($decoded['title']) || empty($decoded['rubric'])) {
            throw new RuntimeException('AI returned an invalid skills-test payload.');
        }

        return $decoded;
    }

    public function scoreSkillsSubmission(\App\Models\SkillTest $test, string $submissionText): array
    {
        $criteria = $test->rubricCriteria->map(function ($criterion) {
            return [
                'id' => $criterion->id,
                'criterion' => $criterion->criterion,
                'description' => $criterion->description,
                'max_points' => (float) $criterion->max_points,
            ];
        })->values()->all();

        $system = <<<'PROMPT'
You are an employment skills-test evaluator. Score ONLY against the supplied analytic rubric. Use evidence from the submission. Do not infer missing work. Return one score per rubric criterion, concise evidence, concise reason, confidence, flags, and a proposed total. Output VALID JSON ONLY.

Shape:
{"criterion_scores":[{"criterion_id":1,"score":20,"evidence":"...","reason":"...","confidence":"high"}],"proposed_total":80,"flags":[]}
PROMPT;

        $response = $this->client()->chat()->create([
            'model' => $this->model(),
            'temperature' => 0.1,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' =>
                    "TASK:\n{$test->instructions}\n\nEXPECTED OUTPUT:\n{$test->expected_output}\n\nRUBRIC:\n"
                    . json_encode($criteria)
                    . "\n\nSUBMISSION:\n"
                    . $submissionText
                ],
            ],
        ]);

        $decoded = $this->decodeJson($response['choices'][0]['message']['content'] ?? '');
        if (!isset($decoded['criterion_scores']) || !isset($decoded['proposed_total'])) {
            throw new RuntimeException('AI returned an invalid skills-score payload.');
        }

        return [
            'model' => $this->model(),
            'criterion_scores' => $decoded['criterion_scores'],
            'proposed_total' => (float) $decoded['proposed_total'],
            'flags' => $decoded['flags'] ?? [],
            'raw_response' => $response['choices'][0]['message']['content'] ?? '',
        ];
    }
}
