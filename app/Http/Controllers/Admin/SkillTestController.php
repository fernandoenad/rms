<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AssessmentAuditLog;
use App\Models\AssessmentIncident;
use App\Models\SkillTest;
use App\Models\SkillTestAssignment;
use App\Models\SkillTestAttempt;
use App\Models\SkillTestHumanScore;
use App\Models\SkillTestSubmission;
use App\Models\Vacancy;
use App\Services\AssessmentAiService;
use App\Services\AssessmentGovernanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SkillTestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function currentUserIsAdmin(): bool
    {
        return (int) optional(optional(auth()->user())->role)->level === 1;
    }

    public function index(AssessmentGovernanceService $governance)
    {
        $tests = SkillTest::with([
                'vacancy:id,position_title',
                'rubricCriteria',
                'assignments:id,skill_test_id',
            ])
            ->withCount('attempts')
            ->orderByDesc('id')
            ->get();

        $readiness = $tests->mapWithKeys(fn ($test) => [
            $test->id => $governance->skillReadiness($test),
        ]);

        return view('admin.skills.index', compact('tests', 'readiness'));
    }

    public function create()
    {
        $vacancies = Vacancy::orderByDesc('cycle')
            ->orderBy('position_title')
            ->get(['id','position_title','cycle']);

        return view('admin.skills.create', compact('vacancies'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'vacancy_id'=>'required|exists:vacancies,id',
            'title'=>'required|string|max:255',
            'code'=>'nullable|string|max:100',
            'instructions'=>'required|string|max:100000',
            'expected_output'=>'nullable|string|max:20000',
            'start_date'=>'required|date',
            'end_date'=>'required|date|after:start_date',
            'duration'=>'required|integer|min:1|max:480',
            'access_mode'=>'required|in:all_taken_in,selected_applicants',
            'submission_modes'=>'required|array|min:1',
            'submission_modes.*'=>'in:inline,file',
            'allowed_extensions'=>'nullable|string',
            'max_file_size_kb'=>'required|integer|min:100|max:51200',
            'ai_scoring'=>'required|boolean',
            'score_release_policy'=>'required|in:hidden,manual,after_close,immediate',
            'status'=>'nullable|boolean',
        ]);
    }

    protected function normalize(array $data): array
    {
        $data['code'] = $data['code'] ?: 'ST-'.now()->format('Ymd-His');
        $data['allowed_extensions'] = collect(explode(',', $data['allowed_extensions'] ?? 'docx'))
            ->map(fn ($x) => strtolower(trim($x)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $data;
    }

    public function store(Request $request, AssessmentGovernanceService $governance)
    {
        $data = $this->normalize($this->validated($request));
        $requestedPublish = (int) ($data['status'] ?? 0) === 1;

        $data['status'] = 0;
        $data['created_by'] = auth()->id();
        $data['approval_status'] = $this->currentUserIsAdmin() ? 'approved' : 'pending';
        $data['approved_by'] = $this->currentUserIsAdmin() ? auth()->id() : null;
        $data['approved_at'] = $this->currentUserIsAdmin() ? now() : null;
        $data['approval_notes'] = $this->currentUserIsAdmin()
            ? 'Auto-approved because the creator is an administrator.'
            : null;

        $data['review_status'] = 'approved';
        $data['reviewed_by'] = auth()->id();
        $data['reviewed_at'] = now();

        $test = SkillTest::create($data);

        $governance->log('skill_test_created', [
            'skill_test_id' => $test->id,
        ], [
            'requested_publish' => $requestedPublish,
        ]);

        return redirect()->route('admin.skills.edit', $test)
            ->with('status', 'Skills test created as a draft. Add or generate the rubric, review readiness, then publish.');
    }

    public function edit(SkillTest $skillTest, AssessmentGovernanceService $governance)
    {
        $skillTest->load([
            'rubricCriteria',
            'vacancy',
        ]);

        $readiness = $governance->skillReadiness($skillTest);
        $incidents = AssessmentIncident::where('skill_test_id', $skillTest->id)
            ->where('status', 'open')
            ->latest()
            ->limit(20)
            ->get();

        $auditLogs = AssessmentAuditLog::where('skill_test_id', $skillTest->id)
            ->with('user:id,name,email')
            ->latest()
            ->limit(30)
            ->get();

        $hasStartedAttempts = $skillTest->attempts()->whereNotNull('started_at')->exists();

        return view('admin.skills.edit', compact(
            'skillTest',
            'readiness',
            'incidents',
            'auditLogs',
            'hasStartedAttempts'
        ));
    }

    public function update(
        Request $request,
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        if ((int) $skillTest->status === 1) {
            return back()->with('status', 'Return the skills test to draft before changing task settings.');
        }

        if ($skillTest->attempts()->whereNotNull('started_at')->exists()) {
            return back()->with('status', 'This administered task is immutable. Create a revision instead.');
        }

        $data = $this->normalize($this->validated($request));
        $requestedPublish = (int) ($data['status'] ?? 0) === 1;
        $data['status'] = 0;

        if (($data['score_release_policy'] ?? $skillTest->score_release_policy) !== $skillTest->score_release_policy) {
            $data['scores_released_at'] = null;
        }

        $taskChanged =
            $skillTest->title !== $data['title']
            || $skillTest->instructions !== $data['instructions']
            || $skillTest->expected_output !== ($data['expected_output'] ?? null);

        if ($taskChanged) {
            // Draft edits stay within the same administered version, but must
            // be reviewed again before publication. Version numbers advance
            // only through the explicit Create Revision workflow.
            $data['review_status'] = 'pending_review';
            $data['reviewed_by'] = null;
            $data['reviewed_at'] = null;
            $data['review_notes'] = null;
        }

        if ($this->currentUserIsAdmin()) {
            $data['approval_status'] = 'approved';
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
            $data['approval_notes'] = 'Approved by administrator after editing the draft.';
        } else {
            $data['approval_status'] = 'pending';
            $data['approved_by'] = null;
            $data['approved_at'] = null;
            $data['approval_notes'] = null;
        }

        $skillTest->update($data);

        $governance->log('skill_test_updated', [
            'skill_test_id' => $skillTest->id,
        ], [
            'task_changed' => $taskChanged,
            'task_version' => $skillTest->fresh()->task_version,
        ]);

        if ($requestedPublish) {
            $readiness = $governance->skillReadiness($skillTest->fresh());

            if (!$readiness['ready']) {
                return back()->with('status', 'Settings saved, but the test remains draft: '.implode(' ', array_slice($readiness['issues'], 0, 8)));
            }

            $skillTest->update(['status' => 1]);

            $governance->log('skill_test_published', [
                'skill_test_id' => $skillTest->id,
            ]);

            return back()->with('status', 'Skills test updated and published after readiness validation.');
        }

        return back()->with('status', 'Skills test settings updated.');
    }

    public function createRevision(
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        $copy = DB::transaction(function () use ($skillTest) {
            $skillTest->load('rubricCriteria');

            $copy = $skillTest->replicate();
            $copy->title = $skillTest->title.' — Revision '.(((int) $skillTest->task_version) + 1);
            $copy->code = ($skillTest->code ?: 'ST-'.$skillTest->id).'-R'.(((int) $skillTest->task_version) + 1);
            $copy->task_version = ((int) $skillTest->task_version) + 1;
            $copy->supersedes_skill_test_id = $skillTest->id;
            $copy->review_status = 'pending_review';
            $copy->reviewed_by = null;
            $copy->reviewed_at = null;
            $copy->review_notes = null;
            $copy->scores_released_at = null;
            $copy->created_by = auth()->id();
            $copy->approval_status = $this->currentUserIsAdmin() ? 'approved' : 'pending';
            $copy->approved_by = $this->currentUserIsAdmin() ? auth()->id() : null;
            $copy->approved_at = $this->currentUserIsAdmin() ? now() : null;
            $copy->approval_notes = $this->currentUserIsAdmin()
                ? 'Auto-approved because the creator is an administrator.'
                : null;
            // Draft revisions default to selected-applicant access so they cannot
            // accidentally appear to every taken-in applicant before review.
            $copy->access_mode = 'selected_applicants';
            $copy->status = 0;
            $copy->start_date = now()->addDay();
            $copy->end_date = now()->addDay()->addHours(2);
            $copy->save();

            foreach ($skillTest->rubricCriteria as $criterion) {
                $copy->allRubricCriteria()->create([
                    'criterion' => $criterion->criterion,
                    'description' => $criterion->description,
                    'max_points' => $criterion->max_points,
                    'sort_order' => $criterion->sort_order,
                    'criterion_version' => ((int) $criterion->criterion_version) + 1,
                    'supersedes_criterion_id' => $criterion->id,
                    'review_status' => 'pending_review',
                    'is_active' => true,
                ]);
            }

            return $copy;
        });

        $governance->log('skill_test_revision_created', [
            'skill_test_id' => $copy->id,
        ], [
            'supersedes_skill_test_id' => $skillTest->id,
            'version' => $copy->task_version,
        ]);

        return redirect()->route('admin.skills.edit', $copy)
            ->with('status', 'Draft revision created. Review the task, rubric, schedule, and readiness before publishing.');
    }

    public function generateAi(
        Request $request,
        SkillTest $skillTest,
        AssessmentAiService $ai,
        AssessmentGovernanceService $governance
    ) {
        if ((int) $skillTest->status === 1) {
            return back()->with('status', 'Return the skills test to draft before replacing task content.');
        }

        if ($skillTest->attempts()->whereNotNull('started_at')->exists()) {
            return back()->with('status', 'Cannot regenerate an administered task. Create a revision instead.');
        }

        $data = $request->validate([
            'additional_context'=>'nullable|string|max:30000',
            'generation_focus'=>'required|in:mixed,duties,technical,situational',
        ]);

        $contextOptions = [
            'use_qualifications'=>$request->boolean('use_qualifications'),
            'use_job_description'=>$request->boolean('use_job_description'),
            'additional_context'=>trim((string)($data['additional_context'] ?? '')),
            'generation_focus'=>$data['generation_focus'],
        ];

        if (!$contextOptions['use_qualifications']
            && !$contextOptions['use_job_description']
            && $contextOptions['additional_context'] === '') {
            return back()->withInput()->with('status','Select at least one vacancy context source or paste additional context.');
        }

        $skillTest->update([
            'ai_context'=>$contextOptions['additional_context'] ?: null,
            'ai_generation_focus'=>$contextOptions['generation_focus'],
            'ai_use_qualifications'=>$contextOptions['use_qualifications'],
            'ai_use_job_description'=>$contextOptions['use_job_description'],
        ]);

        try {
            $payload = $ai->generateSkillsTask(
                $skillTest->vacancy,
                (int) $skillTest->duration,
                $contextOptions
            );

            DB::transaction(function () use ($skillTest, $payload) {
                $oldCriteria = $skillTest->rubricCriteria()->get();
                $skillTest->allRubricCriteria()->where('is_active', true)->update(['is_active' => false]);

                $skillTest->update([
                    'title'=>$payload['title'],
                    'instructions'=>$payload['instructions'],
                    'expected_output'=>$payload['expected_output'] ?? null,
                    'review_status'=>'pending_review',
                    'reviewed_by'=>null,
                    'reviewed_at'=>null,
                    'review_notes'=>null,
                ]);

                foreach ($payload['rubric'] as $i => $criterion) {
                    $old = $oldCriteria->get($i);

                    $skillTest->allRubricCriteria()->create([
                        'criterion'=>$criterion['criterion'],
                        'description'=>$criterion['description'] ?? null,
                        'max_points'=>$criterion['max_points'],
                        'sort_order'=>$i,
                        'criterion_version'=>$old ? ((int) $old->criterion_version + 1) : 1,
                        'supersedes_criterion_id'=>$old?->id,
                        'review_status'=>'pending_review',
                        'is_active'=>true,
                    ]);
                }
            });

            $governance->log('skill_task_ai_generated', [
                'skill_test_id' => $skillTest->id,
            ]);

            return back()->with('status', 'AI-generated task and rubric saved as Pending Review. Approve them before publishing.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('status', 'AI generation failed. No task should be published until the draft is reviewed.');
        }
    }

    public function reviewTask(
        Request $request,
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        if ((int) $skillTest->status === 1 || $skillTest->attempts()->whereNotNull('started_at')->exists()) {
            return back()->with('status', 'Review decisions cannot change after publication/administration.');
        }

        $data = $request->validate([
            'decision'=>'required|in:approved,rejected,pending_review',
            'review_notes'=>'nullable|string|max:5000',
        ]);

        $skillTest->update([
            'review_status'=>$data['decision'],
            'reviewed_by'=>auth()->id(),
            'reviewed_at'=>now(),
            'review_notes'=>$data['review_notes'] ?? null,
        ]);

        $governance->log('skill_task_reviewed', [
            'skill_test_id'=>$skillTest->id,
        ], ['decision'=>$data['decision']]);

        return back()->with('status', 'Task review decision saved.');
    }

    public function saveRubric(
        Request $request,
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        if ((int) $skillTest->status === 1) {
            return back()->with('status', 'Return the skills test to draft before changing the rubric.');
        }

        if ($skillTest->attempts()->whereNotNull('started_at')->exists()) {
            return back()->with('status', 'Rubric is immutable after an attempt starts. Create a task revision instead.');
        }

        $criteria = collect($request->input('criteria', []))
            ->filter(fn ($row) => trim((string) ($row['criterion'] ?? '')) !== '')
            ->values()
            ->all();

        $data = validator(['criteria'=>$criteria], [
            'criteria'=>'required|array|min:1',
            'criteria.*.criterion'=>'required|string|max:255',
            'criteria.*.description'=>'nullable|string|max:10000',
            'criteria.*.max_points'=>'required|numeric|min:0.01|max:100',
        ])->validate();

        $total = collect($data['criteria'])->sum('max_points');
        if (abs($total - 100) > 0.01) {
            return back()->with('status', 'Rubric must total exactly 100 points.');
        }

        DB::transaction(function () use ($skillTest, $data) {
            $oldCriteria = $skillTest->rubricCriteria()->get();
            $skillTest->allRubricCriteria()->where('is_active', true)->update(['is_active'=>false]);

            foreach ($data['criteria'] as $i => $criterion) {
                $old = $oldCriteria->get($i);

                $skillTest->allRubricCriteria()->create($criterion + [
                    'sort_order'=>$i,
                    'criterion_version'=>$old ? ((int) $old->criterion_version + 1) : 1,
                    'supersedes_criterion_id'=>$old?->id,
                    'review_status'=>'approved',
                    'reviewed_by'=>auth()->id(),
                    'reviewed_at'=>now(),
                    'is_active'=>true,
                ]);
            }
        });

        $governance->log('skill_rubric_version_created', [
            'skill_test_id'=>$skillTest->id,
        ]);

        return back()->with('status', 'Rubric saved as a new version and marked approved.');
    }

    public function reviewCriterion(
        Request $request,
        SkillTest $skillTest,
        \App\Models\SkillTestRubricCriterion $criterion,
        AssessmentGovernanceService $governance
    ) {
        abort_unless((int) $criterion->skill_test_id === (int) $skillTest->id && $criterion->is_active, 404);

        if ((int) $skillTest->status === 1 || $skillTest->attempts()->whereNotNull('started_at')->exists()) {
            return back()->with('status', 'Rubric review is locked after publication/administration.');
        }

        $data = $request->validate([
            'decision'=>'required|in:approved,rejected,pending_review',
            'review_notes'=>'nullable|string|max:5000',
        ]);

        $criterion->update([
            'review_status'=>$data['decision'],
            'reviewed_by'=>auth()->id(),
            'reviewed_at'=>now(),
            'review_notes'=>$data['review_notes'] ?? null,
        ]);

        $governance->log('skill_rubric_criterion_reviewed', [
            'skill_test_id'=>$skillTest->id,
            'skill_test_rubric_criterion_id'=>$criterion->id,
        ], ['decision'=>$data['decision']]);

        return back()->with('status', 'Rubric criterion review saved.');
    }

    public function assignApplicants(
        Request $request,
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        $data = $request->validate(['application_codes'=>'required|string']);

        $codes = collect(preg_split('/[\s,;]+/', $data['application_codes']))
            ->map(fn ($x) => trim($x))
            ->filter()
            ->unique()
            ->values();

        $applications = Application::where('vacancy_id', $skillTest->vacancy_id)
            ->whereIn('application_code', $codes)
            ->whereHas('assessment')
            ->get(['id','application_code']);

        $now = now();
        $rows = $applications->map(fn ($application) => [
            'skill_test_id'=>$skillTest->id,
            'application_id'=>$application->id,
            'created_at'=>$now,
            'updated_at'=>$now,
        ])->all();

        $added = count($rows) ? SkillTestAssignment::query()->insertOrIgnore($rows) : 0;

        $governance->log('skill_applicants_assigned', [
            'skill_test_id'=>$skillTest->id,
        ], ['added'=>$added]);

        return back()->with('status', "{$added} applicant assignment(s) added.");
    }

    public function toggleStatus(
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        if (!$skillTest->status) {
            $readiness = $governance->skillReadiness($skillTest);

            if (!$readiness['ready']) {
                return back()->with('status', 'Cannot publish: '.implode(' ', array_slice($readiness['issues'], 0, 8)));
            }

            $skillTest->update(['status'=>1]);
            $governance->log('skill_test_published', ['skill_test_id'=>$skillTest->id]);

            return back()->with('status', 'Skills test published after readiness validation.');
        }

        if ($skillTest->attempts()->whereNotNull('started_at')->exists()) {
            return back()->with('status', 'An administered skills test cannot be returned to draft. Create a revision for future use.');
        }

        $skillTest->update(['status'=>0]);
        $governance->log('skill_test_unpublished', ['skill_test_id'=>$skillTest->id]);

        return back()->with('status', 'Skills test returned to draft.');
    }

    public function results(
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        $dashboard = $skillTest->attempts()
            ->whereNotNull('started_at')
            ->selectRaw('COUNT(*) as attempted')
            ->selectRaw('SUM(CASE WHEN status = 1 AND (expires_at IS NULL OR expires_at > ?) THEN 1 ELSE 0 END) as taking_now', [now()])
            ->selectRaw('SUM(CASE WHEN status = 1 AND expires_at IS NOT NULL AND expires_at <= ? THEN 1 ELSE 0 END) as awaiting_timeout', [now()])
            ->selectRaw('SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as submitted')
            ->selectRaw('SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as voided')
            ->selectRaw('SUM(CASE WHEN status = 2 AND final_score IS NOT NULL THEN 1 ELSE 0 END) as evaluated')
            ->selectRaw('AVG(CASE WHEN status = 2 AND final_score IS NOT NULL THEN final_score END) as mean_final_score')
            ->first();

        $attempts = $skillTest->attempts()
            ->with([
                'application',
                'submissions',
                'aiEvaluations',
                'humanScores',
            ])
            ->whereNotNull('started_at')
            ->whereIn('status', [1,2,3])
            ->orderByRaw('CASE WHEN status = 1 THEN 0 WHEN status = 2 THEN 1 ELSE 2 END')
            ->orderByDesc('started_at')
            ->paginate(25)
            ->withQueryString();

        $incidents = AssessmentIncident::where('skill_test_id', $skillTest->id)
            ->where('status','open')
            ->latest()
            ->get();

        $retakeTests = SkillTest::where('vacancy_id', $skillTest->vacancy_id)
            ->whereIn('status',[0,1])
            ->where('access_mode','selected_applicants')
            ->where('end_date','>',now())
            ->where('id','!=',$skillTest->id)
            ->with(['rubricCriteria','assignments:id,skill_test_id'])
            ->orderBy('start_date')
            ->get()
            ->filter(function ($candidate) use ($governance) {
                if ((int) $candidate->status === 1) {
                    return true;
                }

                $readiness = $governance->skillReadiness($candidate);
                $blocking = collect($readiness['issues'])
                    ->reject(fn ($issue) => $issue === 'Selected-applicant mode is enabled but no applicants are assigned.');

                return $blocking->isEmpty();
            })
            ->values();

        $health = [
            'queue_jobs'=>Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
            'failed_jobs'=>Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            'open_incidents'=>$incidents->count(),
        ];

        $skillTest->load('rubricCriteria');

        $rubricAnalytics = DB::table('skill_test_rubric_criteria as c')
            ->leftJoin('skill_test_human_scores as hs', 'hs.skill_test_rubric_criterion_id', '=', 'c.id')
            ->leftJoin('skill_test_attempts as a', function ($join) {
                $join->on('a.id', '=', 'hs.skill_test_attempt_id')
                    ->where('a.status', '=', 2);
            })
            ->where('c.skill_test_id', $skillTest->id)
            ->where('c.is_active', true)
            ->groupBy('c.id', 'c.criterion', 'c.max_points', 'c.sort_order')
            ->orderBy('c.sort_order')
            ->select('c.id','c.criterion','c.max_points')
            ->selectRaw('COUNT(a.id) as scored_count')
            ->selectRaw('AVG(CASE WHEN a.id IS NOT NULL THEN hs.score END) as mean_score')
            ->get();

        $aiHumanGap = $skillTest->attempts()
            ->where('status',2)
            ->whereNotNull('ai_proposed_score')
            ->whereNotNull('final_score')
            ->selectRaw('AVG(ABS(ai_proposed_score - final_score)) as mean_abs_gap')
            ->value('mean_abs_gap');

        return view('admin.skills.results', compact(
            'skillTest',
            'attempts',
            'dashboard',
            'incidents',
            'retakeTests',
            'health',
            'rubricAnalytics',
            'aiHumanGap'
        ));
    }

    public function finalizeScore(
        Request $request,
        SkillTest $skillTest,
        SkillTestAttempt $attempt,
        AssessmentGovernanceService $governance
    ) {
        abort_unless((int) $attempt->skill_test_id === (int) $skillTest->id, 404);

        if ((int) $attempt->status !== 2) {
            return back()->with('status', 'Only submitted attempts can receive a final human score.');
        }

        $criteria = $skillTest->rubricCriteria()->get();

        if ($criteria->isEmpty()) {
            return back()->with('status', 'No active rubric exists for this skills test.');
        }

        $rules = [];
        foreach ($criteria as $criterion) {
            $rules['scores.'.$criterion->id] = 'required|numeric|min:0|max:'.$criterion->max_points;
            $rules['notes.'.$criterion->id] = 'nullable|string|max:3000';
        }

        $data = $request->validate($rules);
        $total = 0;

        DB::transaction(function () use ($criteria, $data, $attempt, &$total) {
            foreach ($criteria as $criterion) {
                $score = (float) $data['scores'][$criterion->id];
                $total += $score;

                SkillTestHumanScore::updateOrCreate(
                    [
                        'skill_test_attempt_id'=>$attempt->id,
                        'skill_test_rubric_criterion_id'=>$criterion->id,
                    ],
                    [
                        'score'=>$score,
                        'notes'=>$data['notes'][$criterion->id] ?? null,
                        'evaluator_id'=>auth()->id(),
                    ]
                );
            }

            $attempt->update([
                'final_score'=>round($total, 2),
                'finalized_by'=>auth()->id(),
                'evaluated_at'=>now(),
            ]);
        });

        $governance->log('skill_human_score_finalized', [
            'skill_test_id'=>$skillTest->id,
        ], [
            'attempt_id'=>$attempt->id,
            'final_score'=>round($total, 2),
        ]);

        return back()->with('status', 'Human rubric scores saved. Final score: '.number_format($total,2).'/100.');
    }

    public function approveAiScores(
        Request $request,
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        $data = $request->validate([
            'scope'=>'required|in:all,selected',
            'attempt_ids'=>'nullable|array',
            'attempt_ids.*'=>'integer',
        ]);

        $criteria = $skillTest->rubricCriteria()->get();

        if ($criteria->isEmpty()) {
            return back()->with('status', 'Cannot approve AI scores because there is no active rubric.');
        }

        $query = $skillTest->attempts()
            ->where('status', 2)
            ->whereNull('final_score')
            ->whereNotNull('ai_proposed_score');

        if ($data['scope'] === 'selected') {
            $attemptIds = collect($data['attempt_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($attemptIds->isEmpty()) {
                return back()->with('status', 'Select at least one submitted attempt to approve.');
            }

            $query->whereIn('id', $attemptIds);
        }

        $approved = 0;
        $skipped = 0;

        $query->with(['aiEvaluations'])->orderBy('id')->chunkById(100, function ($attempts) use (
            $criteria,
            &$approved,
            &$skipped
        ) {
            foreach ($attempts as $attempt) {
                $evaluation = $attempt->aiEvaluations
                    ->where('status', 'completed')
                    ->sortByDesc('id')
                    ->first();

                if (!$evaluation || !is_array($evaluation->criterion_scores)) {
                    $skipped++;
                    continue;
                }

                $scoresByCriterion = collect($evaluation->criterion_scores)
                    ->filter(fn ($row) => isset($row['criterion_id']))
                    ->keyBy(fn ($row) => (int) $row['criterion_id']);

                $valid = true;
                $total = 0.0;
                $rows = [];

                foreach ($criteria as $criterion) {
                    $row = $scoresByCriterion->get((int) $criterion->id);

                    if (!$row || !isset($row['score']) || !is_numeric($row['score'])) {
                        $valid = false;
                        break;
                    }

                    $score = (float) $row['score'];

                    if ($score < 0 || $score > (float) $criterion->max_points) {
                        $valid = false;
                        break;
                    }

                    $total += $score;
                    $rows[] = [
                        'criterion'=>$criterion,
                        'score'=>$score,
                        'notes'=>'Approved from AI proposal by '.optional(auth()->user())->email,
                    ];
                }

                if (!$valid) {
                    $skipped++;
                    continue;
                }

                $didApprove = DB::transaction(function () use ($attempt, $rows, $total) {
                    $locked = SkillTestAttempt::whereKey($attempt->id)
                        ->where('status', 2)
                        ->whereNull('final_score')
                        ->lockForUpdate()
                        ->first();

                    if (!$locked) {
                        return false;
                    }

                    foreach ($rows as $row) {
                        SkillTestHumanScore::updateOrCreate(
                            [
                                'skill_test_attempt_id'=>$locked->id,
                                'skill_test_rubric_criterion_id'=>$row['criterion']->id,
                            ],
                            [
                                'score'=>$row['score'],
                                'notes'=>$row['notes'],
                                'evaluator_id'=>auth()->id(),
                            ]
                        );
                    }

                    $locked->update([
                        'final_score'=>round($total, 2),
                        'finalized_by'=>auth()->id(),
                        'evaluated_at'=>now(),
                    ]);

                    return true;
                });

                if ($didApprove) {
                    $approved++;
                } else {
                    $skipped++;
                }
            }
        });

        $governance->log('skill_ai_scores_bulk_approved', [
            'skill_test_id'=>$skillTest->id,
        ], [
            'scope'=>$data['scope'],
            'approved'=>$approved,
            'skipped'=>$skipped,
        ]);

        return back()->with(
            'status',
            "{$approved} AI-proposed score(s) approved as human-final scores."
            .($skipped ? " {$skipped} attempt(s) were skipped because the AI rubric result was incomplete or invalid." : '')
        );
    }

    public function releaseScores(
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        if ($skillTest->score_release_policy !== 'manual') {
            return back()->with('status', 'Manual release is available only when the policy is Manual.');
        }

        $skillTest->update(['scores_released_at'=>now()]);
        $governance->log('skill_scores_released', ['skill_test_id'=>$skillTest->id]);

        return back()->with('status', 'Skills test scores released to applicants.');
    }

    public function hideScores(
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        if (in_array($skillTest->score_release_policy, ['immediate','after_close'], true)) {
            return back()->with('status', 'This policy releases automatically. Change the policy to Manual or Hidden first.');
        }

        $skillTest->update(['scores_released_at'=>null]);
        $governance->log('skill_scores_hidden', ['skill_test_id'=>$skillTest->id]);

        return back()->with('status', 'Skills test scores hidden.');
    }

    public function incident(Request $request, SkillTest $skillTest)
    {
        $data = $request->validate([
            'skill_test_attempt_id'=>'nullable|integer|exists:skill_test_attempts,id',
            'type'=>'required|in:connectivity,device,power,proctoring,administrative,other',
            'notes'=>'required|string|max:5000',
        ]);

        $applicationId = null;

        if (!empty($data['skill_test_attempt_id'])) {
            $attempt = $skillTest->attempts()->findOrFail($data['skill_test_attempt_id']);
            $applicationId = $attempt->application_id;
        }

        AssessmentIncident::create([
            'skill_test_id'=>$skillTest->id,
            'skill_test_attempt_id'=>$data['skill_test_attempt_id'] ?? null,
            'application_id'=>$applicationId,
            'type'=>$data['type'],
            'notes'=>$data['notes'],
            'status'=>'open',
            'created_by'=>auth()->id(),
        ]);

        return back()->with('status', 'Skills-test incident recorded.');
    }

    public function resolveIncident(SkillTest $skillTest, AssessmentIncident $incident)
    {
        abort_unless((int) $incident->skill_test_id === (int) $skillTest->id, 404);

        $incident->update([
            'status'=>'resolved',
            'resolved_by'=>auth()->id(),
            'resolved_at'=>now(),
        ]);

        return back()->with('status', 'Incident resolved.');
    }

    public function voidAndRetake(
        Request $request,
        SkillTest $skillTest,
        SkillTestAttempt $attempt,
        AssessmentGovernanceService $governance
    ) {
        abort_unless((int) $attempt->skill_test_id === (int) $skillTest->id, 404);

        $data = $request->validate([
            'reason'=>'required|string|max:2000',
            'retake_skill_test_id'=>'required|integer|exists:skill_tests,id',
        ]);

        $retake = SkillTest::where('vacancy_id', $skillTest->vacancy_id)
            ->whereIn('status',[0,1])
            ->where('access_mode','selected_applicants')
            ->where('end_date','>',now())
            ->findOrFail($data['retake_skill_test_id']);

        if ((int) $retake->id === (int) $skillTest->id) {
            return back()->with('status', 'Choose a different skills test/revision for the retake.');
        }

        if ((int) $retake->status === 0) {
            $readiness = $governance->skillReadiness($retake);
            $blocking = collect($readiness['issues'])
                ->reject(fn ($issue) => $issue === 'Selected-applicant mode is enabled but no applicants are assigned.');

            if ($blocking->isNotEmpty()) {
                return back()->with(
                    'status',
                    'Retake task is not ready: '.$blocking->take(6)->implode(' ')
                );
            }
        }

        if ((int) $attempt->status === 3) {
            return back()->with('status', 'This attempt is already voided.');
        }

        if ($retake->attempts()->where('application_id', $attempt->application_id)->exists()) {
            return back()->with('status', 'This applicant already has an attempt on the selected retake task.');
        }

        DB::transaction(function () use ($attempt, $retake, $data, $skillTest, $governance) {
            $locked = SkillTestAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $retakeLocked = SkillTest::whereKey($retake->id)->lockForUpdate()->firstOrFail();

            SkillTestAssignment::firstOrCreate([
                'skill_test_id'=>$retakeLocked->id,
                'application_id'=>$locked->application_id,
            ]);

            if ((int) $retakeLocked->status === 0) {
                $readiness = $governance->skillReadiness($retakeLocked->fresh());

                if (!$readiness['ready']) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'retake_skill_test_id'=>'Retake task failed readiness after applicant assignment: '.implode(' ', array_slice($readiness['issues'],0,6)),
                    ]);
                }

                $retakeLocked->update(['status'=>1]);

                $governance->log('skill_retake_task_published', [
                    'skill_test_id'=>$retakeLocked->id,
                ], [
                    'authorized_application_id'=>$locked->application_id,
                ]);
            }

            $locked->update([
                'status'=>3,
                'voided_at'=>now(),
                'voided_by'=>auth()->id(),
                'void_reason'=>$data['reason'],
                'retake_skill_test_id'=>$retake->id,
            ]);

            AssessmentIncident::create([
                'skill_test_id'=>$skillTest->id,
                'skill_test_attempt_id'=>$locked->id,
                'application_id'=>$locked->application_id,
                'type'=>'administrative',
                'notes'=>'Attempt voided for controlled retake. Reason: '.$data['reason'],
                'status'=>'resolved',
                'created_by'=>auth()->id(),
                'resolved_by'=>auth()->id(),
                'resolved_at'=>now(),
            ]);

            $governance->log('skill_attempt_voided_for_retake', [
                'skill_test_id'=>$skillTest->id,
            ], [
                'attempt_id'=>$locked->id,
                'retake_skill_test_id'=>$retake->id,
                'reason'=>$data['reason'],
            ]);
        });

        return back()->with('status', 'Original skills attempt retained as voided; retake authorized on the selected published task.');
    }

    public function downloadSubmission(
        SkillTest $skillTest,
        SkillTestSubmission $submission
    ) {
        $submission->loadMissing('attempt');

        abort_unless(
            $submission->attempt
            && (int) $submission->attempt->skill_test_id === (int) $skillTest->id,
            404
        );

        abort_unless($submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);

        $filename = $submission->original_filename ?: basename($submission->file_path);

        return Storage::disk('local')->download($submission->file_path, $filename);
    }

    public function exportCsv(SkillTest $skillTest)
    {
        $filename = Str::slug($skillTest->title).'-skills-results.csv';
        $criteria = $skillTest->rubricCriteria()->get();

        return response()->streamDownload(function () use ($skillTest, $criteria) {
            $handle = fopen('php://output','w');

            $headers = [
                'Application Code','Applicant','Status','Started','Submitted','AI Proposed'
            ];

            foreach ($criteria as $criterion) {
                $headers[] = $criterion->criterion.' Score';
                $headers[] = $criterion->criterion.' Note';
            }

            $headers = array_merge($headers, ['Human Final','Evaluated','Void Reason']);
            fputcsv($handle, $headers);

            $skillTest->attempts()
                ->with(['application','humanScores'])
                ->whereNotNull('started_at')
                ->orderBy('id')
                ->chunkById(500, function ($attempts) use ($handle, $criteria) {
                    foreach ($attempts as $attempt) {
                        $row = [
                            optional($attempt->application)->application_code,
                            optional($attempt->application)->getFullname(),
                            match ((int) $attempt->status) {
                                1=>'In progress',
                                2=>'Submitted',
                                3=>'Voided',
                                default=>'Not started',
                            },
                            $attempt->started_at?->toIso8601String(),
                            $attempt->submitted_at?->toIso8601String(),
                            $attempt->ai_proposed_score,
                        ];

                        $human = $attempt->humanScores->keyBy('skill_test_rubric_criterion_id');
                        foreach ($criteria as $criterion) {
                            $score = $human->get($criterion->id);
                            $row[] = optional($score)->score;
                            $row[] = optional($score)->notes;
                        }

                        $row[] = $attempt->final_score;
                        $row[] = $attempt->evaluated_at?->toIso8601String();
                        $row[] = $attempt->void_reason;

                        fputcsv($handle, $row);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type'=>'text/csv']);
    }
}
