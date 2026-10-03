<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AssessmentAccommodation;
use App\Models\AssessmentAiGenerationRun;
use App\Models\AssessmentGroup;
use App\Models\AssessmentGroupAttemptLock;
use App\Models\AssessmentIncident;
use App\Models\AssessmentTimeExtension;
use App\Models\Exam;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\Vacancy;
use App\Jobs\GenerateWrittenExamItemsBatch;
use App\Services\AssessmentAnalyticsService;
use App\Services\AssessmentGovernanceService;
use App\Services\AssessmentScoreSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssessmentGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function currentUserIsAdmin(): bool
    {
        return (int) optional(optional(auth()->user())->role)->level === 1;
    }

    protected function ensureNotArchived(AssessmentGroup $assessmentGroup): void
    {
        abort_if($assessmentGroup->archived_at, 403, 'This assessment group is archived and frozen.');
    }

    public function index()
    {
        $groups = AssessmentGroup::with('vacancy:id,position_title')
            ->withCount(['exams', 'attemptLocks'])
            ->orderByDesc('id')
            ->get();

        return view('admin.assessment_groups.index', compact('groups'));
    }

    public function create(AssessmentScoreSyncService $scoreSync)
    {
        $vacancies = Vacancy::orderByDesc('id')
            ->get(['id', 'position_title', 'cycle', 'template_id']);

        $scoreCriteriaByVacancy = $vacancies->mapWithKeys(
            fn ($vacancy) => [$vacancy->id => $scoreSync->criteriaForVacancy($vacancy)]
        );

        return view('admin.assessment_groups.create', compact('vacancies','scoreCriteriaByVacancy'));
    }

    protected function validated(Request $request, ?AssessmentGroup $group = null): array
    {
        $codeRule = Rule::unique('assessment_groups', 'code')
            ->where(fn ($q) => $q->where('vacancy_id', $request->vacancy_id));

        if ($group) {
            $codeRule->ignore($group->id);
        }

        return $request->validate([
            'vacancy_id' => 'required|exists:vacancies,id',
            'title' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:100', $codeRule],
            'expected_sets' => 'required|integer|min:1|max:26',
            'default_duration' => 'nullable|integer|min:1|max:480',
            'status' => 'required|boolean',
            'score_release_policy' => 'required|in:hidden,manual,after_close,immediate',
            'assessment_score_key' => 'nullable|string|max:255',
            'blueprint_item_count' => 'nullable|integer|min:1|max:300',
            'solo_unistructural' => 'nullable|integer|min:0|max:100',
            'solo_multistructural' => 'nullable|integer|min:0|max:100',
            'solo_relational' => 'nullable|integer|min:0|max:100',
            'solo_extended_abstract' => 'nullable|integer|min:0|max:100',
            'difficulty_easy' => 'nullable|integer|min:0|max:100',
            'difficulty_moderate' => 'nullable|integer|min:0|max:100',
            'difficulty_difficult' => 'nullable|integer|min:0|max:100',
            'blueprint_competencies' => 'nullable|string|max:10000',
        ]);
    }

    protected function buildBlueprint(array $data): ?array
    {
        $itemCount = (int) ($data['blueprint_item_count'] ?? 0);
        if ($itemCount < 1) {
            return null;
        }

        $solo = [
            'unistructural' => (int) ($data['solo_unistructural'] ?? 0),
            'multistructural' => (int) ($data['solo_multistructural'] ?? 0),
            'relational' => (int) ($data['solo_relational'] ?? 0),
            'extended_abstract' => (int) ($data['solo_extended_abstract'] ?? 0),
        ];

        $difficulty = [
            'easy' => (int) ($data['difficulty_easy'] ?? 0),
            'moderate' => (int) ($data['difficulty_moderate'] ?? 0),
            'difficult' => (int) ($data['difficulty_difficult'] ?? 0),
        ];

        if (array_sum($solo) !== 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'solo_unistructural' => 'SOLO blueprint percentages must total exactly 100%.',
            ]);
        }

        if (array_sum($difficulty) !== 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'difficulty_easy' => 'Difficulty blueprint percentages must total exactly 100%.',
            ]);
        }

        $competencies = collect(preg_split('/\r\n|\r|\n/', (string) ($data['blueprint_competencies'] ?? '')))
            ->map(function ($line) {
                $parts = array_map('trim', explode('|', $line, 2));
                if (($parts[0] ?? '') === '') return null;

                return [
                    'name' => $parts[0],
                    'items' => max(0, (int) ($parts[1] ?? 0)),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'item_count' => $itemCount,
            'solo_distribution' => $solo,
            'difficulty_distribution' => $difficulty,
            'competencies' => $competencies,
        ];
    }

    protected function createSetPlaceholders(
        AssessmentGroup $group,
        int $targetCount,
        int $defaultDuration,
        AssessmentGovernanceService $governance
    ): void {
        $current = $group->exams()->count();

        for ($i = $current; $i < $targetCount; $i++) {
            $setCode = $governance->nextSetCode($group->fresh());
            $exam = Exam::create([
                'vacancy_id' => $group->vacancy_id,
                'created_by' => auth()->id(),
                'approval_status' => $this->currentUserIsAdmin() ? 'approved' : 'pending',
                'approved_by' => $this->currentUserIsAdmin() ? auth()->id() : null,
                'approved_at' => $this->currentUserIsAdmin() ? now() : null,
                'approval_notes' => $this->currentUserIsAdmin()
                    ? 'Auto-approved because the creator is an administrator.'
                    : null,
                'assessment_group_id' => $group->id,
                'title' => $group->title . ' - Set ' . $setCode,
                'code' => ($group->code ?: 'WG-'.$group->id) . '-' . $setCode,
                'set_code' => $setCode,
                'enrollment_key' => strtoupper(Str::random(12)),
                'start_date' => null,
                'end_date' => null,
                'duration' => $defaultDuration,
                'access_mode' => 'all_taken_in',
                'shuffle_items' => true,
                'shuffle_options' => true,
                'status' => 0,
            ]);

            $governance->log('set_placeholder_created', [
                'assessment_group_id' => $group->id,
                'exam_id' => $exam->id,
            ], ['set_code' => $setCode]);
        }
    }

    public function store(
        Request $request,
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
    ) {
        $data = $this->validated($request);

        if (!$scoreSync->criterionExistsForVacancy((int)$data['vacancy_id'], $data['assessment_score_key'] ?? null)) {
            return back()->withInput()->withErrors([
                'assessment_score_key'=>'The selected applicant-score criterion does not belong to this vacancy template.',
            ]);
        }

        $blueprint = $this->buildBlueprint($data);

        $group = DB::transaction(function () use ($data, $blueprint, $governance) {
            $group = AssessmentGroup::create([
                'vacancy_id' => $data['vacancy_id'],
                'title' => $data['title'],
                'code' => $data['code'],
                'expected_sets' => $data['expected_sets'],
                'blueprint' => $blueprint,
                'blueprint_version' => 1,
                'status' => $data['status'],
                'score_release_policy' => $data['score_release_policy'],
                'assessment_score_key' => $data['assessment_score_key'] ?? null,
                'scores_released_at' => null,
                'scores_synced_at' => null,
            ]);

            $this->createSetPlaceholders(
                $group,
                (int) $data['expected_sets'],
                (int) ($data['default_duration'] ?? 60),
                $governance
            );

            $governance->log('assessment_group_created', [
                'assessment_group_id' => $group->id,
            ], ['expected_sets' => (int) $data['expected_sets']]);

            return $group;
        });

        return redirect()->route('admin.assessment_groups.edit', $group)
            ->with('status', 'Assessment group created with draft set placeholders.');
    }

    public function edit(
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
    ) {
        $vacancies = Vacancy::orderByDesc('id')
            ->get(['id', 'position_title', 'cycle']);

        $assessmentGroup->load([
            'vacancy',
            'exams' => fn ($q) => $q->withCount(['attempts', 'assignments'])->orderBy('set_code'),
        ]);

        // Avoid Laravel's per-parent eager-load limit window query here. Some
        // production MySQL/MariaDB configurations reject the generated
        // ROW_NUMBER() OVER (PARTITION BY ...) statement with SQLSTATE 1140.
        // These are single-group queries, so ordinary LIMIT clauses are enough.
        $incidents = $assessmentGroup->incidents()
            ->where('status', 'open')
            ->latest()
            ->limit(20)
            ->get();

        $auditLogs = $assessmentGroup->auditLogs()
            ->with('user:id,name,email')
            ->latest()
            ->limit(30)
            ->get();

        // Preserve the relationship API expected by the existing Blade view.
        $assessmentGroup->setRelation('incidents', $incidents);
        $assessmentGroup->setRelation('auditLogs', $auditLogs);

        $readiness = $assessmentGroup->exams
            ->mapWithKeys(fn ($exam) => [$exam->id => $governance->readiness($exam)]);

        $scoreCriteria = $scoreSync->criteriaForVacancy($assessmentGroup->vacancy);

        return view('admin.assessment_groups.edit', compact(
            'assessmentGroup',
            'vacancies',
            'readiness',
            'scoreCriteria'
        ));
    }

    public function update(
        Request $request,
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
    ) {
        $this->ensureNotArchived($assessmentGroup);
        $data = $this->validated($request, $assessmentGroup);

        if (!$scoreSync->criterionExistsForVacancy((int)$data['vacancy_id'], $data['assessment_score_key'] ?? null)) {
            return back()->withInput()->withErrors([
                'assessment_score_key'=>'The selected applicant-score criterion does not belong to this vacancy template.',
            ]);
        }

        $blueprint = $this->buildBlueprint($data);

        if ($assessmentGroup->exams()->exists()
            && (int) $data['vacancy_id'] !== (int) $assessmentGroup->vacancy_id) {
            return back()->withInput()->withErrors([
                'vacancy_id' => 'The position cannot be changed after exam sets have been added.',
            ]);
        }

        $started = $assessmentGroup->exams()->whereHas('attempts', fn ($q) =>
            $q->whereNotNull('started_at')
        )->exists();

        $existingSets = $assessmentGroup->exams()->count();

        if ($started && (int) $data['expected_sets'] !== (int) $assessmentGroup->expected_sets) {
            return back()->withInput()->withErrors([
                'expected_sets' => 'The number of sets is locked after any applicant has started.',
            ]);
        }

        if ((int) $data['expected_sets'] < $existingSets) {
            return back()->withInput()->withErrors([
                'expected_sets' => "This group already has {$existingSets} set(s). Existing sets are never silently deleted.",
            ]);
        }

        $blueprintChanged = $assessmentGroup->blueprint != $blueprint;

        if ($blueprintChanged && $started) {
            return back()->withInput()->withErrors([
                'blueprint_item_count' => 'The shared blueprint is locked after any applicant starts the assessment.',
            ]);
        }

        if ($blueprintChanged && $assessmentGroup->exams()->where('status', 1)->exists()) {
            return back()->withInput()->withErrors([
                'blueprint_item_count' => 'Return all sets to draft before changing the shared blueprint.',
            ]);
        }

        DB::transaction(function () use ($assessmentGroup, $data, $blueprint, $governance) {
            $blueprintChanged = $assessmentGroup->blueprint != $blueprint;

            $assessmentGroup->update([
                'vacancy_id' => $data['vacancy_id'],
                'title' => $data['title'],
                'code' => $data['code'],
                'expected_sets' => $data['expected_sets'],
                'blueprint' => $blueprint,
                'blueprint_version' => $blueprintChanged
                    ? ((int) $assessmentGroup->blueprint_version + 1)
                    : $assessmentGroup->blueprint_version,
                'status' => $data['status'],
                'score_release_policy' => $data['score_release_policy'],
                'assessment_score_key' => $data['assessment_score_key'] ?? null,
                'scores_released_at' => $data['score_release_policy'] === 'manual'
                    ? $assessmentGroup->scores_released_at
                    : null,
                'scores_synced_at' => (
                    ($data['assessment_score_key'] ?? null) === $assessmentGroup->assessment_score_key
                    && $data['score_release_policy'] === $assessmentGroup->score_release_policy
                ) ? $assessmentGroup->scores_synced_at : null,
            ]);

            $this->createSetPlaceholders(
                $assessmentGroup,
                (int) $data['expected_sets'],
                (int) ($data['default_duration'] ?? 60),
                $governance
            );

            $governance->log('assessment_group_updated', [
                'assessment_group_id' => $assessmentGroup->id,
            ], [
                'blueprint_changed' => $blueprintChanged,
                'expected_sets' => (int) $data['expected_sets'],
            ]);
        });

        return redirect()->route('admin.assessment_groups.edit', $assessmentGroup)
            ->with('status', 'Assessment group and blueprint updated.');
    }

    public function generateAllSets(
        Request $request,
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureNotArchived($assessmentGroup);

        $data = $request->validate([
            'additional_context'=>'nullable|string|max:30000',
            'generation_focus'=>'required|in:mixed,duties,technical,situational',
        ]);

        $blueprint = $assessmentGroup->blueprint ?: [];
        $count = (int)($blueprint['item_count'] ?? 0);
        $distribution = $blueprint['solo_distribution'] ?? [];

        if ($count < 1 || array_sum(array_map('intval',$distribution)) !== 100) {
            return back()->with(
                'status',
                'Configure the shared blueprint first: item count and SOLO distribution totaling 100% are required for automatic set generation.'
            );
        }

        $contextOptions = [
            'use_qualifications'=>$request->boolean('use_qualifications'),
            'use_job_description'=>$request->boolean('use_job_description'),
            'additional_context'=>trim((string)($data['additional_context'] ?? '')),
            'generation_focus'=>$data['generation_focus'],
            'blueprint'=>$blueprint,
        ];

        if (!$contextOptions['use_qualifications']
            && !$contextOptions['use_job_description']
            && $contextOptions['additional_context'] === '') {
            return back()->with('status','Select at least one vacancy context source or provide additional context.');
        }

        $sets = $assessmentGroup->exams()
            ->where('status',0)
            ->whereDoesntHave('attempts',fn($q)=>$q->whereNotNull('started_at'))
            ->withCount('writtenExams')
            ->orderBy('set_code')
            ->get();

        $targets = $sets->filter(fn($set)=>(int)$set->written_exams_count === 0)->values();

        if ($targets->isEmpty()) {
            return back()->with(
                'status',
                'No empty draft sets are available. Existing generated/manual items were left untouched.'
            );
        }

        $queuedRuns = 0;
        $queuedBatches = 0;
        $batchSize = 10;

        foreach ($targets as $exam) {
            $exam->update([
                'ai_context'=>$contextOptions['additional_context'] ?: null,
                'ai_generation_focus'=>$contextOptions['generation_focus'],
                'ai_use_qualifications'=>$contextOptions['use_qualifications'],
                'ai_use_job_description'=>$contextOptions['use_job_description'],
            ]);

            $batchCount = (int)ceil($count/$batchSize);
            $run = AssessmentAiGenerationRun::create([
                'exam_id'=>$exam->id,
                'requested_count'=>$count,
                'generated_count'=>0,
                'failed_batches'=>0,
                'batch_count'=>$batchCount,
                'completed_batches'=>0,
                'solo_distribution'=>$distribution,
                'context_options'=>$contextOptions,
                'status'=>'queued',
                'requested_by'=>auth()->id(),
            ]);

            for ($remaining=$count; $remaining>0; $remaining-=$batchSize) {
                GenerateWrittenExamItemsBatch::dispatch($run->id,min($batchSize,$remaining));
                $queuedBatches++;
            }

            $queuedRuns++;
        }

        $governance->log('written_group_ai_generation_queued', [
            'assessment_group_id'=>$assessmentGroup->id,
        ], [
            'set_count'=>$queuedRuns,
            'batch_count'=>$queuedBatches,
            'items_per_set'=>$count,
        ]);

        return back()->with(
            'status',
            "AI generation queued for {$queuedRuns} empty set(s), {$count} items per set. Review and approve the generated items before publishing."
        );
    }

    public function destroy(AssessmentGroup $assessmentGroup)
    {
        abort_unless($this->currentUserIsAdmin(), 403);

        if ($assessmentGroup->exams()->whereHas('attempts')->exists()
            || $assessmentGroup->attemptLocks()->exists()) {
            return back()->with(
                'status',
                'Cannot delete this Written Assessment Group because an applicant has already started or been locked to one of its sets. Archive it instead.'
            );
        }

        DB::transaction(function () use ($assessmentGroup) {
            // Exams use nullOnDelete for the group FK, so delete the empty sets
            // explicitly to avoid leaving orphaned standalone tests.
            $assessmentGroup->exams()->get()->each->delete();
            $assessmentGroup->delete();
        });

        return redirect()->route('admin.assessment_groups.index')
            ->with('status','Written Assessment Group and all of its unattempted sets were deleted.');
    }

    public function createEquivalentSet(
        Request $request,
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureNotArchived($assessmentGroup);
        if ($assessmentGroup->exams()->whereHas('attempts', fn ($q) => $q->whereNotNull('started_at'))->exists()) {
            return back()->with('status', 'Equivalent set structure is locked after attempts exist.');
        }

        $data = $request->validate([
            'source_exam_id' => 'nullable|integer|exists:exams,id',
        ]);

        $source = null;
        if (!empty($data['source_exam_id'])) {
            $source = $assessmentGroup->exams()->findOrFail($data['source_exam_id']);
        }

        $setCode = $governance->nextSetCode($assessmentGroup);
        $exam = new Exam();

        if ($source) {
            $exam = $source->replicate();
            $exam->start_date = null;
            $exam->end_date = null;
            $exam->ai_context = $source->ai_context;
            $exam->ai_generation_focus = $source->ai_generation_focus;
            $exam->ai_use_qualifications = $source->ai_use_qualifications;
            $exam->ai_use_job_description = $source->ai_use_job_description;
        } else {
            $exam->vacancy_id = $assessmentGroup->vacancy_id;
            $exam->duration = 60;
            $exam->access_mode = 'all_taken_in';
            $exam->shuffle_items = true;
            $exam->shuffle_options = true;
        }

        $exam->created_by = auth()->id();
        $exam->approval_status = $this->currentUserIsAdmin() ? 'approved' : 'pending';
        $exam->approved_by = $this->currentUserIsAdmin() ? auth()->id() : null;
        $exam->approved_at = $this->currentUserIsAdmin() ? now() : null;
        $exam->approval_notes = $this->currentUserIsAdmin()
            ? 'Auto-approved because the creator is an administrator.'
            : null;
        $exam->assessment_group_id = $assessmentGroup->id;
        $exam->set_code = $setCode;
        $exam->title = $assessmentGroup->title . ' - Set ' . $setCode;
        $exam->code = ($assessmentGroup->code ?: 'WG-'.$assessmentGroup->id) . '-' . $setCode;
        $exam->enrollment_key = strtoupper(Str::random(12));
        $exam->status = 0;
        $exam->save();

        if ((int) $assessmentGroup->expected_sets < $assessmentGroup->exams()->count()) {
            $assessmentGroup->update(['expected_sets' => $assessmentGroup->exams()->count()]);
        }

        $governance->log('equivalent_set_created', [
            'assessment_group_id' => $assessmentGroup->id,
            'exam_id' => $exam->id,
        ], [
            'source_exam_id' => $source?->id,
            'set_code' => $setCode,
        ]);

        return redirect()->route('admin.assessments.items.index', $exam)
            ->with('status', 'Equivalent draft set created. Generate or add distinct items using the shared blueprint.');
    }

    public function results(
        AssessmentGroup $assessmentGroup,
        AssessmentAnalyticsService $analytics
    ) {
        $assessmentGroup->load([
            'vacancy:id,position_title',
            'exams' => fn ($q) => $q->orderBy('set_code'),
        ]);

        $dashboard = ExamAttempt::query()
            ->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
            ->where('exams.assessment_group_id', $assessmentGroup->id)
            ->whereNotNull('exam_attempts.started_at')
            ->selectRaw('COUNT(*) as attempted')
            ->selectRaw('SUM(CASE WHEN exam_attempts.status = 1 AND (exam_attempts.expires_at IS NULL OR exam_attempts.expires_at > ?) THEN 1 ELSE 0 END) as taking_now', [now()])
            ->selectRaw('SUM(CASE WHEN exam_attempts.status = 1 AND exam_attempts.expires_at IS NOT NULL AND exam_attempts.expires_at <= ? THEN 1 ELSE 0 END) as awaiting_timeout_finalization', [now()])
            ->selectRaw('SUM(CASE WHEN exam_attempts.status = 2 THEN 1 ELSE 0 END) as submitted')
            ->selectRaw('SUM(CASE WHEN exam_attempts.status = 3 THEN 1 ELSE 0 END) as voided')
            ->first();

        $dashboard->completion_rate = (int) $dashboard->attempted > 0
            ? round(((int) $dashboard->submitted / (int) $dashboard->attempted) * 100, 1)
            : 0;

        $attempts = ExamAttempt::whereHas('exam', function ($query) use ($assessmentGroup) {
                $query->where('assessment_group_id', $assessmentGroup->id);
            })
            ->with([
                'application:id,application_code,first_name,middle_name,last_name',
                'exam:id,assessment_group_id,title,set_code',
                'events' => fn ($q) => $q->orderByDesc('event_at')->limit(20),
                'timeExtensions' => fn ($q) => $q->with('creator:id,name,email')->orderByDesc('id'),
                'incidents' => fn ($q) => $q->where('status','open')->latest(),
            ])
            ->whereNotNull('started_at')
            ->whereIn('status', [1, 2, 3])
            ->orderByRaw('CASE WHEN status = 1 THEN 0 WHEN status = 2 THEN 1 ELSE 2 END')
            ->orderByDesc('started_at')
            ->paginate(50)
            ->withQueryString();

        $setSummary = $analytics->setSummary($assessmentGroup);
        $comparabilityWarnings = $analytics->comparabilityWarnings($setSummary);

        $health = [
            'queue_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
            'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            'recent_answer_activity' => DB::table('exam_attempt_answers as a')
                ->join('exam_attempts as t', 't.id', '=', 'a.exam_attempt_id')
                ->join('exams as e', 'e.id', '=', 't.exam_id')
                ->where('e.assessment_group_id', $assessmentGroup->id)
                ->where('a.updated_at', '>=', now()->subMinute())
                ->count(),
            'open_incidents' => AssessmentIncident::where('assessment_group_id', $assessmentGroup->id)
                ->where('status', 'open')
                ->count(),
        ];

        return view('admin.assessment_groups.results', compact(
            'assessmentGroup',
            'attempts',
            'dashboard',
            'setSummary',
            'comparabilityWarnings',
            'health'
        ));
    }

    public function analytics(
        AssessmentGroup $assessmentGroup,
        AssessmentAnalyticsService $analytics
    ) {
        $assessmentGroup->load(['vacancy:id,position_title', 'exams' => fn ($q) => $q->orderBy('set_code')]);
        $setSummary = $analytics->setSummary($assessmentGroup);
        $comparabilityWarnings = $analytics->comparabilityWarnings($setSummary);
        $itemAnalytics = [];

        foreach ($assessmentGroup->exams as $exam) {
            $itemAnalytics[$exam->id] = $analytics->itemAnalytics($exam);
        }

        return view('admin.assessment_groups.analytics', compact(
            'assessmentGroup',
            'setSummary',
            'comparabilityWarnings',
            'itemAnalytics'
        ));
    }

    public function releaseScores(
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
    ) {
        $this->ensureNotArchived($assessmentGroup);
        if ($assessmentGroup->score_release_policy !== 'manual') {
            return back()->with('status', 'Manual release is available only when the score release policy is set to Manual.');
        }

        $assessmentGroup->update(['scores_released_at' => now()]);
        $sync = $scoreSync->syncWrittenGroup($assessmentGroup->fresh());
        $governance->log('scores_released', [
            'assessment_group_id' => $assessmentGroup->id,
        ], [
            'applicant_scores_synced'=>$sync['synced'],
            'applicant_scores_skipped'=>$sync['skipped'],
        ]);

        return back()->with(
            'status',
            'Applicant score visibility has been released. '
            .$sync['synced'].' official written score(s) were synchronized to applicant assessment records.'
            .($sync['skipped'] ? ' '.$sync['skipped'].' record(s) were skipped because no compatible applicant assessment/template mapping was available.' : '')
        );
    }

    public function hideScores(
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureNotArchived($assessmentGroup);
        if (in_array($assessmentGroup->score_release_policy, ['immediate', 'after_close'], true)) {
            return back()->with('status', 'This score policy releases automatically. Change the policy to Manual or Hidden to suppress scores.');
        }

        $assessmentGroup->update(['scores_released_at' => null]);
        $governance->log('scores_hidden', [
            'assessment_group_id' => $assessmentGroup->id,
        ]);

        return back()->with('status', 'Applicant scores are hidden.');
    }

    public function incident(Request $request, AssessmentGroup $assessmentGroup)
    {
        $data = $request->validate([
            'exam_attempt_id' => 'nullable|integer|exists:exam_attempts,id',
            'application_id' => 'nullable|integer|exists:applications,id',
            'type' => 'required|in:connectivity,device,power,proctoring,administrative,other',
            'notes' => 'required|string|max:5000',
        ]);

        if (!empty($data['exam_attempt_id'])) {
            $attempt = ExamAttempt::whereHas('exam', fn ($q) =>
                $q->where('assessment_group_id', $assessmentGroup->id)
            )->findOrFail($data['exam_attempt_id']);

            $data['application_id'] = $attempt->application_id;
        }

        AssessmentIncident::create($data + [
            'assessment_group_id' => $assessmentGroup->id,
            'created_by' => auth()->id(),
        ]);

        return back()->with('status', 'Assessment incident recorded.');
    }

    public function resolveIncident(AssessmentGroup $assessmentGroup, AssessmentIncident $incident)
    {
        abort_unless((int) $incident->assessment_group_id === (int) $assessmentGroup->id, 404);

        $incident->update([
            'status' => 'resolved',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        return back()->with('status', 'Incident resolved.');
    }

    public function voidAndRetake(
        Request $request,
        AssessmentGroup $assessmentGroup,
        ExamAttempt $attempt,
        AssessmentGovernanceService $governance
    ) {
        abort_unless((int) optional($attempt->exam)->assessment_group_id === (int) $assessmentGroup->id, 404);

        $data = $request->validate([
            'reason' => 'required|string|max:2000',
            'retake_exam_id' => 'required|integer|exists:exams,id',
        ]);

        $retakeExam = $assessmentGroup->exams()->findOrFail($data['retake_exam_id']);

        if ((int) $retakeExam->id === (int) $attempt->exam_id) {
            return back()->with('status', 'Choose a different equivalent set for the retake.');
        }

        if ((int) $retakeExam->status !== 1) {
            return back()->with('status', 'Publish and validate the retake set before assigning an applicant to it.');
        }

        if ((int) $attempt->status === 3) {
            return back()->with('status', 'This attempt is already voided.');
        }

        if ($retakeExam->attempts()->where('application_id', $attempt->application_id)->exists()) {
            return back()->with('status', 'This applicant already has an attempt on the selected retake set.');
        }

        DB::transaction(function () use ($assessmentGroup, $attempt, $retakeExam, $data, $governance) {
            $locked = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'status' => 3,
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $data['reason'],
                'retake_exam_id' => $retakeExam->id,
            ]);

            AssessmentGroupAttemptLock::where('assessment_group_id', $assessmentGroup->id)
                ->where('application_id', $locked->application_id)
                ->delete();

            AssessmentGroupAttemptLock::create([
                'assessment_group_id' => $assessmentGroup->id,
                'application_id' => $locked->application_id,
                'exam_id' => $retakeExam->id,
                'exam_attempt_id' => null,
            ]);

            if ($retakeExam->access_mode === 'selected_applicants') {
                ExamAssignment::firstOrCreate([
                    'exam_id' => $retakeExam->id,
                    'application_id' => $locked->application_id,
                ]);
            }

            AssessmentIncident::create([
                'assessment_group_id' => $assessmentGroup->id,
                'exam_attempt_id' => $locked->id,
                'application_id' => $locked->application_id,
                'type' => 'administrative',
                'notes' => 'Attempt voided for controlled retake. Reason: '.$data['reason'],
                'status' => 'resolved',
                'created_by' => auth()->id(),
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
            ]);

            $governance->log('attempt_voided_for_retake', [
                'assessment_group_id' => $assessmentGroup->id,
                'exam_id' => $locked->exam_id,
            ], [
                'attempt_id' => $locked->id,
                'retake_exam_id' => $retakeExam->id,
                'reason' => $data['reason'],
            ]);
        });

        return back()->with('status', 'Attempt retained as voided and applicant locked to the selected retake set.');
    }

    public function saveAccommodation(
        Request $request,
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureNotArchived($assessmentGroup);

        $data = $request->validate([
            'application_code'=>'required|string|max:100',
            'extra_minutes'=>'required|integer|min:0|max:240',
            'large_text'=>'nullable|boolean',
            'notes'=>'nullable|string|max:3000',
        ]);

        $application = Application::where('vacancy_id',$assessmentGroup->vacancy_id)
            ->where('application_code',$data['application_code'])
            ->whereHas('assessment')
            ->first();

        if (!$application) {
            return back()->with('status','No taken-in applicant with that application code was found for this position.');
        }

        AssessmentAccommodation::updateOrCreate(
            [
                'application_id'=>$application->id,
                'assessment_group_id'=>$assessmentGroup->id,
            ],
            [
                'exam_id'=>null,
                'skill_test_id'=>null,
                'extra_minutes'=>(int)$data['extra_minutes'],
                'large_text'=>$request->boolean('large_text'),
                'notes'=>$data['notes'] ?? null,
                'approved_by'=>auth()->id(),
            ]
        );

        $governance->log('written_accommodation_saved', [
            'assessment_group_id'=>$assessmentGroup->id,
        ], [
            'application_id'=>$application->id,
            'extra_minutes'=>(int)$data['extra_minutes'],
            'large_text'=>$request->boolean('large_text'),
        ]);

        return back()->with('status','Applicant accommodation saved for all sets in this written assessment.');
    }

    public function pause(
        Request $request,
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance
    ) {
        $data = $request->validate(['reason'=>'required|string|max:3000']);

        if ($assessmentGroup->archived_at) {
            return back()->with('status', 'Archived assessments cannot be paused.');
        }

        $assessmentGroup->update([
            'is_paused'=>true,
            'pause_reason'=>$data['reason'],
            'paused_at'=>now(),
            'paused_by'=>auth()->id(),
        ]);

        $governance->log('assessment_group_paused', [
            'assessment_group_id'=>$assessmentGroup->id,
        ], ['reason'=>$data['reason'], 'scope'=>'new_starts']);

        return back()->with('status', 'New written-assessment starts are paused. Existing in-progress attempts may continue.');
    }

    public function resume(
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance
    ) {
        $assessmentGroup->update([
            'is_paused'=>false,
            'pause_reason'=>null,
            'paused_at'=>null,
            'paused_by'=>null,
        ]);

        $governance->log('assessment_group_resumed', [
            'assessment_group_id'=>$assessmentGroup->id,
        ]);

        return back()->with('status', 'Written assessment resumed for new starts.');
    }

    public function archive(
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance
    ) {
        $inProgress = ExamAttempt::whereHas('exam', fn ($q) =>
                $q->where('assessment_group_id',$assessmentGroup->id)
            )
            ->where('status',1)
            ->exists();

        if ($inProgress) {
            return back()->with('status', 'Cannot archive while written attempts are in progress.');
        }

        $assessmentGroup->update([
            'status'=>false,
            'is_paused'=>false,
            'archived_at'=>now(),
            'archived_by'=>auth()->id(),
        ]);

        foreach ($assessmentGroup->exams()->get() as $exam) {
            $governance->snapshotExam($exam,'group_archived');
        }

        $governance->log('assessment_group_archived', [
            'assessment_group_id'=>$assessmentGroup->id,
        ]);

        return redirect()->route('admin.assessment_center.index')
            ->with('status', 'Written assessment archived and frozen.');
    }

    public function extendAttempt(
        Request $request,
        AssessmentGroup $assessmentGroup,
        ExamAttempt $attempt,
        AssessmentGovernanceService $governance
    ) {
        abort_unless((int) optional($attempt->exam)->assessment_group_id === (int) $assessmentGroup->id, 404);

        $data = $request->validate([
            'minutes'=>'required|integer|min:1|max:240',
            'reason'=>'required|string|max:3000',
        ]);

        $extension = DB::transaction(function () use ($attempt, $data) {
            $locked = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->status !== 1 || !$locked->expires_at) {
                return null;
            }

            $locked->update([
                'expires_at'=>$locked->expires_at->copy()->addMinutes((int)$data['minutes']),
            ]);

            return AssessmentTimeExtension::create([
                'exam_attempt_id'=>$locked->id,
                'minutes'=>(int)$data['minutes'],
                'reason'=>$data['reason'],
                'created_by'=>auth()->id(),
            ]);
        });

        if (!$extension) {
            return back()->with('status', 'Only active, timed attempts can receive an extension.');
        }

        $governance->log('written_attempt_time_extended', [
            'assessment_group_id'=>$assessmentGroup->id,
            'exam_id'=>$attempt->exam_id,
        ], [
            'attempt_id'=>$attempt->id,
            'minutes'=>(int)$data['minutes'],
            'reason'=>$data['reason'],
        ]);

        return back()->with('status', "Added {$data['minutes']} minute(s) to the selected written attempt.");
    }

    public function exportCsv(AssessmentGroup $assessmentGroup)
    {
        $filename = Str::slug($assessmentGroup->title) . '-official-results.csv';

        return response()->streamDownload(function () use ($assessmentGroup) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Application Code',
                'Applicant',
                'Set',
                'Started',
                'Submitted',
                'Status',
                'Raw Score',
                'Total Items',
                'Percentage',
                'Void Reason',
            ]);

            ExamAttempt::whereHas('exam', fn ($q) =>
                    $q->where('assessment_group_id', $assessmentGroup->id)
                )
                ->with(['application:id,application_code,first_name,middle_name,last_name', 'exam:id,set_code'])
                ->whereNotNull('started_at')
                ->orderBy('id')
                ->chunkById(500, function ($attempts) use ($handle) {
                    foreach ($attempts as $attempt) {
                        fputcsv($handle, [
                            optional($attempt->application)->application_code,
                            optional($attempt->application)->getFullname(),
                            optional($attempt->exam)->set_code,
                            $attempt->started_at?->toIso8601String(),
                            $attempt->ended_at?->toIso8601String(),
                            match ((int) $attempt->status) {
                                1 => 'In progress',
                                2 => 'Submitted',
                                3 => 'Voided',
                                default => 'Not started',
                            },
                            $attempt->correct_answers,
                            $attempt->total_items,
                            $attempt->percentage,
                            $attempt->void_reason,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
