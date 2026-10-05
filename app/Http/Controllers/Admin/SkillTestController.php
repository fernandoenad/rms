<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AssessmentAccommodation;
use App\Models\AssessmentAuditLog;
use App\Models\AssessmentIncident;
use App\Models\AssessmentScoreChange;
use App\Models\AssessmentTimeExtension;
use App\Models\SkillTest;
use App\Models\SkillTestGroup;
use App\Models\SkillTestGroupAttemptLock;
use App\Models\SkillTestAssignment;
use App\Models\SkillTestAttempt;
use App\Models\SkillTestAttemptEvent;
use App\Models\SkillTestHumanScore;
use App\Models\SkillTestSubmission;
use App\Models\Vacancy;
use App\Services\AssessmentAiService;
use App\Services\AssessmentGovernanceService;
use App\Services\AssessmentScoreSyncService;
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

    protected function ensureNotArchived(SkillTest $skillTest): void
    {
        abort_if($skillTest->archived_at, 403, 'This skills test is archived and frozen.');
    }

    public function index(AssessmentGovernanceService $governance)
    {
        $tests = SkillTest::with([
                'vacancy:id,position_title',
                'skillTestGroup:id,title',
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

    public function create(Request $request, AssessmentScoreSyncService $scoreSync)
    {
        $vacancies = Vacancy::orderByDesc('id')
            ->get(['id','position_title','cycle','template_id']);

        $scoreCriteriaByVacancy = $vacancies->mapWithKeys(
            fn ($vacancy) => [$vacancy->id => $scoreSync->criteriaForVacancy($vacancy)]
        );

        $groups = SkillTestGroup::with('vacancy:id,position_title')
            ->whereNull('archived_at')
            ->orderByDesc('id')
            ->get();

        $selectedGroup = $request->filled('skill_test_group_id')
            ? $groups->firstWhere('id',(int)$request->skill_test_group_id)
            : null;

        return view('admin.skills.create', compact(
            'vacancies','scoreCriteriaByVacancy','groups','selectedGroup'
        ));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'vacancy_id'=>'required|exists:vacancies,id',
            'skill_test_group_id'=>'nullable|exists:skill_test_groups,id',
            'set_code'=>'nullable|string|max:50',
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
            'assessment_score_key'=>'nullable|string|max:255',
            'status'=>'nullable|boolean',
            'ai_generated_task'=>'nullable|boolean',
            'generated_rubric'=>'nullable|string|max:50000',
            'additional_context'=>'nullable|string|max:30000',
            'generation_focus'=>'nullable|in:mixed,duties,technical,situational',
            'use_qualifications'=>'nullable|boolean',
            'use_job_description'=>'nullable|boolean',
        ]);
    }

    protected function validateGroupSelection(array $data): array
    {
        if (empty($data['skill_test_group_id'])) {
            $data['skill_test_group_id'] = null;
            $data['set_code'] = null;
            return $data;
        }

        $group = SkillTestGroup::findOrFail($data['skill_test_group_id']);

        if ((int)$group->vacancy_id !== (int)$data['vacancy_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'skill_test_group_id'=>'The Skills Test group must belong to the selected position.',
            ]);
        }

        if (blank($data['set_code'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'set_code'=>'A set code is required for a Skills Test inside an equivalent-set group.',
            ]);
        }

        $duplicate = SkillTest::where('skill_test_group_id',$group->id)
            ->where('set_code',$data['set_code'])
            ->exists();

        if ($duplicate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'set_code'=>'This set code is already used in the selected Skills Test group.',
            ]);
        }

        // Group-level rules control official score release and mapping.
        $data['score_release_policy'] = $group->score_release_policy;
        $data['assessment_score_key'] = null;

        return $data;
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

    public function store(
        Request $request,
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
    ) {
        $data = $this->normalize($this->validateGroupSelection($this->validated($request)));

        if (!$data['skill_test_group_id']
            && !$scoreSync->criterionExistsForVacancy((int)$data['vacancy_id'], $data['assessment_score_key'] ?? null)) {
            return back()->withInput()->withErrors([
                'assessment_score_key'=>'The selected applicant-score criterion does not belong to this vacancy template.',
            ]);
        }
        $requestedPublish = (int) ($data['status'] ?? 0) === 1;
        $aiGenerated = (bool) ($data['ai_generated_task'] ?? false);
        $generatedRubricJson = $data['generated_rubric'] ?? null;

        $generatedRubric = [];
        if ($aiGenerated) {
            $decoded = json_decode((string) $generatedRubricJson, true);

            if (!is_array($decoded) || empty($decoded)) {
                return back()->withInput()->withErrors([
                    'generated_rubric'=>'The AI-generated rubric is missing or invalid. Generate the task again before creating the test.',
                ]);
            }

            $rubricData = validator(['criteria'=>$decoded], [
                'criteria'=>'required|array|min:1|max:20',
                'criteria.*.criterion'=>'required|string|max:255',
                'criteria.*.description'=>'nullable|string|max:10000',
                'criteria.*.max_points'=>'required|numeric|min:0.01|max:100',
            ])->validate();

            $total = collect($rubricData['criteria'])->sum('max_points');
            if (abs($total - 100) > 0.01) {
                return back()->withInput()->withErrors([
                    'generated_rubric'=>'The AI-generated rubric must total exactly 100 points. Generate the task again.',
                ]);
            }

            $generatedRubric = $rubricData['criteria'];
        }

        $data['status'] = 0;
        $data['created_by'] = auth()->id();
        $data['approval_status'] = $this->currentUserIsAdmin() ? 'approved' : 'pending';
        $data['approved_by'] = $this->currentUserIsAdmin() ? auth()->id() : null;
        $data['approved_at'] = $this->currentUserIsAdmin() ? now() : null;
        $data['approval_notes'] = $this->currentUserIsAdmin()
            ? 'Auto-approved because the creator is an administrator.'
            : null;

        $data['review_status'] = $aiGenerated ? 'pending_review' : 'approved';
        $data['reviewed_by'] = $aiGenerated ? null : auth()->id();
        $data['reviewed_at'] = $aiGenerated ? null : now();
        $data['review_notes'] = $aiGenerated
            ? 'AI-generated task requires human review before publication.'
            : null;

        $data['ai_context'] = trim((string) ($data['additional_context'] ?? '')) ?: null;
        $data['ai_generation_focus'] = $data['generation_focus'] ?? 'mixed';
        $data['ai_use_qualifications'] = (bool) ($data['use_qualifications'] ?? true);
        $data['ai_use_job_description'] = (bool) ($data['use_job_description'] ?? true);

        unset(
            $data['ai_generated_task'],
            $data['generated_rubric'],
            $data['additional_context'],
            $data['generation_focus'],
            $data['use_qualifications'],
            $data['use_job_description']
        );

        $test = DB::transaction(function () use ($data, $generatedRubric, $aiGenerated) {
            $test = SkillTest::create($data);

            if ($aiGenerated) {
                foreach ($generatedRubric as $i => $criterion) {
                    $test->allRubricCriteria()->create([
                        'criterion'=>$criterion['criterion'],
                        'description'=>$criterion['description'] ?? null,
                        'max_points'=>$criterion['max_points'],
                        'sort_order'=>$i,
                        'criterion_version'=>1,
                        'review_status'=>'pending_review',
                        'reviewed_by'=>null,
                        'reviewed_at'=>null,
                        'review_notes'=>'AI-generated rubric criterion requires human review.',
                        'is_active'=>true,
                    ]);
                }
            }

            return $test;
        });

        $governance->log('skill_test_created', [
            'skill_test_id' => $test->id,
        ], [
            'requested_publish' => $requestedPublish,
            'ai_generated' => $aiGenerated,
            'generated_rubric_count' => count($generatedRubric),
        ]);

        return redirect()->route('admin.skills.edit', $test)
            ->with(
                'status',
                $aiGenerated
                    ? 'AI-assisted skills test created as a draft. Review and approve the generated task and rubric before publishing.'
                    : 'Skills test created as a draft. Add or generate the rubric, review readiness, then publish.'
            );
    }

    public function generateCreateDraft(
        Request $request,
        AssessmentAiService $ai
    ) {
        $data = $request->validate([
            'vacancy_id'=>'required|integer|exists:vacancies,id',
            'duration'=>'required|integer|min:1|max:480',
            'additional_context'=>'nullable|string|max:30000',
            'generation_focus'=>'required|in:mixed,duties,technical,situational',
            'use_qualifications'=>'nullable|boolean',
            'use_job_description'=>'nullable|boolean',
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
            return response()->json([
                'message'=>'Select at least one vacancy context source or provide additional context.',
                'errors'=>['context'=>['Select at least one vacancy context source or provide additional context.']],
            ], 422);
        }

        $vacancy = Vacancy::findOrFail($data['vacancy_id']);

        try {
            $payload = $ai->generateSkillsTask(
                $vacancy,
                (int)$data['duration'],
                $contextOptions
            );

            $rubric = collect($payload['rubric'] ?? [])
                ->map(fn ($row) => [
                    'criterion'=>trim((string)($row['criterion'] ?? '')),
                    'description'=>trim((string)($row['description'] ?? '')),
                    'max_points'=>is_numeric($row['max_points'] ?? null)
                        ? (float)$row['max_points']
                        : null,
                ])
                ->filter(fn ($row) => $row['criterion'] !== '' && $row['max_points'] !== null)
                ->values();

            if ($rubric->isEmpty()
                || abs((float)$rubric->sum('max_points') - 100.0) > 0.01
                || blank($payload['title'] ?? null)
                || blank($payload['instructions'] ?? null)) {
                return response()->json([
                    'message'=>'AI returned an incomplete task or a rubric that does not total 100 points. Please generate again.',
                ], 422);
            }

            return response()->json([
                'title'=>trim((string)$payload['title']),
                'instructions'=>trim((string)$payload['instructions']),
                'expected_output'=>trim((string)($payload['expected_output'] ?? '')),
                'rubric'=>$rubric->all(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            $message = 'AI generation failed. Please try again or enter the task manually.';

            if (config('app.debug')) {
                $message .= ' '.$e->getMessage();
            }

            return response()->json([
                'message'=>$message,
                'error_type'=>class_basename($e),
            ], 502);
        }
    }

    public function edit(
        SkillTest $skillTest,
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
    ) {
        $skillTest->load([
            'rubricCriteria',
            'vacancy',
            'skillTestGroup',
        ]);

        $groups = SkillTestGroup::with('vacancy:id,position_title')
            ->whereNull('archived_at')
            ->orWhere('id',$skillTest->skill_test_group_id)
            ->orderByDesc('id')
            ->get();

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

        $scoreCriteria = $scoreSync->criteriaForVacancy($skillTest->vacancy);

        return view('admin.skills.edit', compact(
            'skillTest',
            'readiness',
            'incidents',
            'auditLogs',
            'hasStartedAttempts',
            'scoreCriteria',
            'groups'
        ));
    }

    public function update(
        Request $request,
        SkillTest $skillTest,
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
    ) {
        $this->ensureNotArchived($skillTest);
        if ((int) $skillTest->status === 1) {
            return back()->with('status', 'Return the skills test to draft before changing task settings.');
        }

        if ($skillTest->attempts()->whereNotNull('started_at')->exists()) {
            return back()->with('status', 'This administered task is immutable. Create a revision instead.');
        }

        $data = $this->normalize($this->validated($request));

        if (!empty($data['skill_test_group_id'])) {
            $group = SkillTestGroup::findOrFail($data['skill_test_group_id']);

            if ((int)$group->vacancy_id !== (int)$data['vacancy_id']) {
                return back()->withInput()->withErrors([
                    'skill_test_group_id'=>'The Skills Test group must belong to the selected position.',
                ]);
            }

            if (blank($data['set_code'])) {
                return back()->withInput()->withErrors(['set_code'=>'Set code is required.']);
            }

            $duplicate = SkillTest::where('skill_test_group_id',$group->id)
                ->where('set_code',$data['set_code'])
                ->whereKeyNot($skillTest->id)
                ->exists();

            if ($duplicate) {
                return back()->withInput()->withErrors(['set_code'=>'This set code is already used in the group.']);
            }

            $data['score_release_policy'] = $group->score_release_policy;
            $data['assessment_score_key'] = null;
        } else {
            $data['skill_test_group_id'] = null;
            $data['set_code'] = null;
        }

        if (!$data['skill_test_group_id']
            && !$scoreSync->criterionExistsForVacancy((int)$data['vacancy_id'], $data['assessment_score_key'] ?? null)) {
            return back()->withInput()->withErrors([
                'assessment_score_key'=>'The selected applicant-score criterion does not belong to this vacancy template.',
            ]);
        }

        $requestedPublish = (int) ($data['status'] ?? 0) === 1;
        $data['status'] = 0;

        if (($data['score_release_policy'] ?? $skillTest->score_release_policy) !== $skillTest->score_release_policy) {
            $data['scores_released_at'] = null;
            $data['scores_synced_at'] = null;
        }

        if (($data['assessment_score_key'] ?? null) !== $skillTest->assessment_score_key) {
            $data['scores_synced_at'] = null;
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
            $governance->recordSkillExposure($skillTest->fresh());
            $governance->snapshotSkill($skillTest->fresh(),'published');

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
        $this->ensureNotArchived($skillTest);
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
            $copy->scores_synced_at = null;
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
        $this->ensureNotArchived($skillTest);
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
        $this->ensureNotArchived($skillTest);
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

        if ($data['decision'] === 'approved') {
            $governance->syncSkillTaskToBank($skillTest->fresh());
        }

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
        $this->ensureNotArchived($skillTest);
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
        $this->ensureNotArchived($skillTest);
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

        $conflictingApplicationIds = collect();

        if ($skillTest->skill_test_group_id && $applications->isNotEmpty()) {
            $siblingIds = SkillTest::where('skill_test_group_id',$skillTest->skill_test_group_id)
                ->whereKeyNot($skillTest->id)
                ->pluck('id');

            if ($siblingIds->isNotEmpty()) {
                $conflictingApplicationIds = SkillTestAssignment::whereIn('skill_test_id',$siblingIds)
                    ->whereIn('application_id',$applications->pluck('id'))
                    ->pluck('application_id')
                    ->unique();
            }
        }

        $eligible = $applications
            ->reject(fn ($application) => $conflictingApplicationIds->contains($application->id))
            ->values();

        $now = now();
        $rows = $eligible->map(fn ($application) => [
            'skill_test_id'=>$skillTest->id,
            'application_id'=>$application->id,
            'created_at'=>$now,
            'updated_at'=>$now,
        ])->all();

        $added = count($rows) ? SkillTestAssignment::query()->insertOrIgnore($rows) : 0;
        $conflicts = $conflictingApplicationIds->count();
        $already = max(0,$eligible->count()-$added);

        $governance->log('skill_applicants_assigned', [
            'skill_test_id'=>$skillTest->id,
        ], ['added'=>$added,'conflicts'=>$conflicts,'already_assigned'=>$already]);

        $message = "{$added} applicant assignment(s) added.";
        if ($already) $message .= " {$already} were already assigned to this set.";
        if ($conflicts) $message .= " {$conflicts} skipped because they are assigned to another equivalent set.";

        return back()->with('status',$message);
    }

    public function destroy(SkillTest $skillTest)
    {
        abort_unless($this->currentUserIsAdmin(), 403);

        if ($skillTest->attempts()->exists()) {
            return back()->with('status','Cannot delete a Skills Test after any applicant has attempted it. Archive it instead.');
        }

        if (SkillTestGroupAttemptLock::where('skill_test_id',$skillTest->id)->exists()) {
            return back()->with('status','Cannot delete this set because an applicant is already locked to it.');
        }

        $group = $skillTest->skillTestGroup;

        if ($group && $group->skillTests()->count() <= 1) {
            return back()->with('status','This is the last set in the group. Delete the Skills Test Group instead.');
        }

        DB::transaction(function () use ($skillTest,$group) {
            $skillTest->delete();

            if ($group) {
                $group->update([
                    'expected_sets'=>$group->skillTests()->count(),
                ]);
            }
        });

        return redirect()->route('admin.skills.index')
            ->with('status','Skills Test deleted. No applicant attempts were affected.');
    }

    public function approve(
        Request $request,
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureNotArchived($skillTest);
        abort_unless($this->currentUserIsAdmin(), 403);

        $data = $request->validate([
            'approval_notes'=>'nullable|string|max:5000',
        ]);

        $skillTest->update([
            'approval_status'=>'approved',
            'approved_by'=>auth()->id(),
            'approved_at'=>now(),
            'approval_notes'=>$data['approval_notes'] ?? 'Approved by administrator.',
        ]);

        $governance->log('skill_test_approved', [
            'skill_test_id'=>$skillTest->id,
        ], [
            'creator_id'=>$skillTest->created_by,
        ]);

        return back()->with('status', 'Skills test approved.');
    }

    public function toggleStatus(
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureNotArchived($skillTest);
        if (!$skillTest->status) {
            $readiness = $governance->skillReadiness($skillTest);

            if (!$readiness['ready']) {
                return back()->with('status', 'Cannot publish: '.implode(' ', array_slice($readiness['issues'], 0, 8)));
            }

            $skillTest->update(['status'=>1]);
            $governance->recordSkillExposure($skillTest->fresh());
            $governance->snapshotSkill($skillTest->fresh(),'published');
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

    public function preview(
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        $skillTest->load(['vacancy','rubricCriteria']);
        $readiness = $governance->skillReadiness($skillTest);
        $infrastructure = $governance->infrastructureReadiness();

        return view('admin.skills.preview', compact('skillTest','readiness','infrastructure'));
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
                'timeExtensions' => fn ($q) => $q->with('creator:id,name,email')->orderByDesc('id'),
                'scoreChanges' => fn ($q) => $q->with('changer:id,name,email')->orderByDesc('id'),
                'incidents' => fn ($q) => $q->where('status','open')->latest(),
            ])
            ->whereNotNull('started_at')
            ->whereIn('status', [1,2,3])
            ->orderByRaw('CASE WHEN status = 1 THEN 0 WHEN status = 2 THEN 1 ELSE 2 END')
            ->orderByDesc('started_at')
            ->paginate(25)
            ->withQueryString();

        $attemptIds = $attempts->getCollection()->pluck('id')->all();
        if ($attemptIds) {
            $eventsByAttempt = SkillTestAttemptEvent::whereIn('skill_test_attempt_id', $attemptIds)
                ->orderByDesc('event_at')
                ->get()
                ->groupBy('skill_test_attempt_id')
                ->map(fn ($events) => $events->take(20)->values());

            $attempts->getCollection()->each(function ($attempt) use ($eventsByAttempt) {
                $attempt->setRelation(
                    'events',
                    $eventsByAttempt->get($attempt->id, collect())
                );
            });
        }

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
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
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

        if ($attempt->final_score !== null) {
            $rules['change_reason'] = 'required|string|max:3000';
        } else {
            $rules['change_reason'] = 'nullable|string|max:3000';
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

            $previous = $attempt->final_score;

            $attempt->update([
                'final_score'=>round($total, 2),
                'finalized_by'=>auth()->id(),
                'evaluated_at'=>now(),
            ]);

            AssessmentScoreChange::create([
                'skill_test_attempt_id'=>$attempt->id,
                'previous_score'=>$previous,
                'new_score'=>round($total,2),
                'source'=>'human',
                'reason'=>$previous === null
                    ? ($data['change_reason'] ?? 'Initial human rubric finalization.')
                    : $data['change_reason'],
                'changed_by'=>auth()->id(),
            ]);
        });

        $attempt->refresh();
        $synced = $scoreSync->syncSkillAttempt($attempt);

        $governance->log('skill_human_score_finalized', [
            'skill_test_id'=>$skillTest->id,
        ], [
            'attempt_id'=>$attempt->id,
            'final_score'=>round($total, 2),
            'applicant_score_synced'=>$synced,
        ]);

        return back()->with(
            'status',
            'Human rubric scores saved. Final score: '.number_format($total,2).'/100.'
            .($synced ? ' The official score was also written to the applicant assessment record.' : '')
        );
    }

    public function approveAiScores(
        Request $request,
        SkillTest $skillTest,
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
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
        $synced = 0;

        $query->with(['aiEvaluations'])->orderBy('id')->chunkById(100, function ($attempts) use (
            $criteria,
            $scoreSync,
            &$approved,
            &$skipped,
            &$synced
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

                    $previous = $locked->final_score;

                    $locked->update([
                        'final_score'=>round($total, 2),
                        'finalized_by'=>auth()->id(),
                        'evaluated_at'=>now(),
                    ]);

                    AssessmentScoreChange::create([
                        'skill_test_attempt_id'=>$locked->id,
                        'previous_score'=>$previous,
                        'new_score'=>round($total,2),
                        'source'=>'ai_approved',
                        'reason'=>'AI rubric proposal approved in bulk by an administrator/evaluator.',
                        'changed_by'=>auth()->id(),
                    ]);

                    return true;
                });

                if ($didApprove) {
                    $approved++;
                    $attempt->refresh();
                    if ($scoreSync->syncSkillAttempt($attempt)) {
                        $synced++;
                    }
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
            'applicant_scores_synced'=>$synced,
        ]);

        return back()->with(
            'status',
            "{$approved} AI-proposed score(s) approved as human-final scores."
            .($synced ? " {$synced} official score(s) were also written to applicant assessment records." : '')
            .($skipped ? " {$skipped} attempt(s) were skipped because the AI rubric result was incomplete or invalid." : '')
        );
    }

    public function releaseScores(
        SkillTest $skillTest,
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
    ) {
        $this->ensureNotArchived($skillTest);

        if ($skillTest->skill_test_group_id) {
            return redirect()->route('admin.skill_groups.edit',$skillTest->skill_test_group_id)
                ->with('status','Equivalent-set score release is controlled at the Skills Test Group level.');
        }

        if ($skillTest->score_release_policy !== 'manual') {
            return back()->with('status', 'Manual release is available only when the policy is Manual.');
        }

        $skillTest->update(['scores_released_at'=>now()]);
        $sync = $scoreSync->syncSkillTest($skillTest->fresh());
        $governance->snapshotSkill($skillTest->fresh(),'scores_released');
        $governance->log('skill_scores_released', ['skill_test_id'=>$skillTest->id], [
            'applicant_scores_synced'=>$sync['synced'],
            'applicant_scores_skipped'=>$sync['skipped'],
        ]);

        return back()->with(
            'status',
            'Skills test scores released to applicants. '
            .$sync['synced'].' official skills score(s) were synchronized to applicant assessment records.'
            .($sync['skipped'] ? ' '.$sync['skipped'].' record(s) were skipped because no compatible applicant assessment/template mapping was available.' : '')
        );
    }

    public function hideScores(
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureNotArchived($skillTest);

        if ($skillTest->skill_test_group_id) {
            return redirect()->route('admin.skill_groups.edit',$skillTest->skill_test_group_id)
                ->with('status','Equivalent-set score release is controlled at the Skills Test Group level.');
        }
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

            if ($skillTest->skill_test_group_id
                && (int)$skillTest->skill_test_group_id === (int)$retakeLocked->skill_test_group_id) {
                SkillTestGroupAttemptLock::where('skill_test_group_id',$skillTest->skill_test_group_id)
                    ->where('application_id',$locked->application_id)
                    ->update([
                        'skill_test_id'=>$retakeLocked->id,
                        'skill_test_attempt_id'=>null,
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

    public function saveAccommodation(
        Request $request,
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureNotArchived($skillTest);

        $data = $request->validate([
            'application_code'=>'required|string|max:100',
            'extra_minutes'=>'required|integer|min:0|max:240',
            'large_text'=>'nullable|boolean',
            'notes'=>'nullable|string|max:3000',
        ]);

        $application = Application::where('vacancy_id',$skillTest->vacancy_id)
            ->where('application_code',$data['application_code'])
            ->whereHas('assessment')
            ->first();

        if (!$application) {
            return back()->with('status','No taken-in applicant with that application code was found for this position.');
        }

        AssessmentAccommodation::updateOrCreate(
            [
                'application_id'=>$application->id,
                'skill_test_id'=>$skillTest->id,
            ],
            [
                'assessment_group_id'=>null,
                'exam_id'=>null,
                'extra_minutes'=>(int)$data['extra_minutes'],
                'large_text'=>$request->boolean('large_text'),
                'notes'=>$data['notes'] ?? null,
                'approved_by'=>auth()->id(),
            ]
        );

        $governance->log('skill_accommodation_saved', [
            'skill_test_id'=>$skillTest->id,
        ], [
            'application_id'=>$application->id,
            'extra_minutes'=>(int)$data['extra_minutes'],
            'large_text'=>$request->boolean('large_text'),
        ]);

        return back()->with('status','Applicant accommodation saved for this skills test.');
    }

    public function pause(
        Request $request,
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        $data = $request->validate(['reason'=>'required|string|max:3000']);

        if ($skillTest->archived_at) {
            return back()->with('status', 'Archived skills tests cannot be paused.');
        }

        $skillTest->update([
            'is_paused'=>true,
            'pause_reason'=>$data['reason'],
            'paused_at'=>now(),
            'paused_by'=>auth()->id(),
        ]);

        $governance->log('skill_test_paused', [
            'skill_test_id'=>$skillTest->id,
        ], ['reason'=>$data['reason'], 'scope'=>'new_starts']);

        return back()->with('status', 'New skills-test starts are paused. Existing in-progress attempts may continue.');
    }

    public function resume(
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        $skillTest->update([
            'is_paused'=>false,
            'pause_reason'=>null,
            'paused_at'=>null,
            'paused_by'=>null,
        ]);

        $governance->log('skill_test_resumed', ['skill_test_id'=>$skillTest->id]);

        return back()->with('status', 'Skills test resumed for new starts.');
    }

    public function archive(
        SkillTest $skillTest,
        AssessmentGovernanceService $governance
    ) {
        if ($skillTest->attempts()->where('status',1)->exists()) {
            return back()->with('status', 'Cannot archive while skills-test attempts are in progress.');
        }

        $skillTest->update([
            'status'=>0,
            'is_paused'=>false,
            'archived_at'=>now(),
            'archived_by'=>auth()->id(),
        ]);

        $governance->snapshotSkill($skillTest->fresh(),'archived');
        $governance->log('skill_test_archived', ['skill_test_id'=>$skillTest->id]);

        return redirect()->route('admin.assessment_center.index')
            ->with('status', 'Skills test archived and frozen.');
    }

    public function extendAttempt(
        Request $request,
        SkillTest $skillTest,
        SkillTestAttempt $attempt,
        AssessmentGovernanceService $governance
    ) {
        abort_unless((int)$attempt->skill_test_id === (int)$skillTest->id, 404);

        $data = $request->validate([
            'minutes'=>'required|integer|min:1|max:240',
            'reason'=>'required|string|max:3000',
        ]);

        $extension = DB::transaction(function () use ($attempt, $data) {
            $locked = SkillTestAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ((int)$locked->status !== 1 || !$locked->expires_at) {
                return null;
            }

            $locked->update([
                'expires_at'=>$locked->expires_at->copy()->addMinutes((int)$data['minutes']),
            ]);

            return AssessmentTimeExtension::create([
                'skill_test_attempt_id'=>$locked->id,
                'minutes'=>(int)$data['minutes'],
                'reason'=>$data['reason'],
                'created_by'=>auth()->id(),
            ]);
        });

        if (!$extension) {
            return back()->with('status', 'Only active, timed attempts can receive an extension.');
        }

        $governance->log('skill_attempt_time_extended', [
            'skill_test_id'=>$skillTest->id,
        ], [
            'attempt_id'=>$attempt->id,
            'minutes'=>(int)$data['minutes'],
            'reason'=>$data['reason'],
        ]);

        return back()->with('status', "Added {$data['minutes']} minute(s) to the selected skills-test attempt.");
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
