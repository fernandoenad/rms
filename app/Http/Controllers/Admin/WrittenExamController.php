<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAssignment;
use App\Models\Application;
use App\Models\AssessmentGroup;
use App\Models\AssessmentGroupAttemptLock;
use App\Models\AssessmentAiGenerationRun;
use App\Models\Vacancy;
use App\Models\WrittenExam;
use App\Models\WrittenExamOption;
use App\Jobs\GenerateWrittenExamItemsBatch;
use App\Services\AssessmentAiService;
use App\Services\AssessmentGovernanceService;
use App\Services\AssessmentScoreSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WrittenExamController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function currentUserIsAdmin(): bool
    {
        return (int) optional(optional(auth()->user())->role)->level === 1;
    }

    protected function ensureNotArchived(Exam $exam): void
    {
        abort_if($exam->archived_at, 403, 'This assessment is archived and frozen.');
    }

    public function index(Request $request)
    {
        $vacancies = Vacancy::query()
            ->whereHas('exams')
            ->orderByDesc('id')
            ->get(['id','position_title','cycle']);

        $selectedVacancyId = $request->integer('vacancy_id');

        $exams = Exam::with(['vacancy:id,position_title', 'assessmentGroup:id,title'])
            ->withCount(['writtenExams', 'attempts'])
            ->when($selectedVacancyId, fn ($query) =>
                $query->where('vacancy_id', $selectedVacancyId)
            )
            ->orderByDesc('id')
            ->get();

        return view('admin.assessments.index', compact(
            'exams',
            'vacancies',
            'selectedVacancyId'
        ));
    }

    public function create(Request $request, AssessmentScoreSyncService $scoreSync)
    {
        $vacancies = Vacancy::orderByDesc('id')
            ->get(['id', 'position_title', 'cycle', 'template_id']);

        $scoreCriteriaByVacancy = $vacancies->mapWithKeys(
            fn ($vacancy) => [$vacancy->id => $scoreSync->criteriaForVacancy($vacancy)]
        );

        $groups = AssessmentGroup::with('vacancy:id,position_title')
            ->where('status', 1)
            ->orderBy('title')
            ->get();

        $selectedGroup = null;
        if ($request->filled('assessment_group_id')) {
            $selectedGroup = $groups->firstWhere('id', (int) $request->assessment_group_id);
        }

        return view('admin.assessments.create', compact('vacancies', 'groups', 'selectedGroup', 'scoreCriteriaByVacancy'));
    }

    public function edit(Exam $exam, AssessmentScoreSyncService $scoreSync)
    {
        $vacancies = Vacancy::orderByDesc('id')
            ->get(['id', 'position_title', 'cycle']);

        $groups = AssessmentGroup::with('vacancy:id,position_title')
            ->where('status', 1)
            ->orWhere('id', $exam->assessment_group_id)
            ->orderBy('title')
            ->get();

        $scoreCriteria = $scoreSync->criteriaForVacancy($exam->vacancy);

        return view('admin.assessments.edit', compact('exam', 'vacancies', 'groups', 'scoreCriteria'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'vacancy_id' => 'required|exists:vacancies,id',
            'assessment_group_id' => 'nullable|exists:assessment_groups,id',
            'set_code' => 'nullable|string|max:50',
            'title' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'duration' => 'required|integer|min:1',
            'access_mode' => 'required|in:all_taken_in,selected_applicants',
            'shuffle_items' => 'required|boolean',
            'shuffle_options' => 'required|boolean',
            'status' => 'required|integer|in:0,1',
            'assessment_score_key' => 'nullable|string|max:255',
        ]);
    }

    protected function validateGroupSelection(Request $request, array $data, ?Exam $exam = null): array
    {
        if (empty($data['assessment_group_id'])) {
            $data['assessment_group_id'] = null;
            $data['set_code'] = null;
            return $data;
        }

        $data['assessment_score_key'] = null;

        $group = AssessmentGroup::findOrFail($data['assessment_group_id']);

        if ((int) $group->vacancy_id !== (int) $data['vacancy_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'assessment_group_id' => 'The assessment group must belong to the selected position.',
            ]);
        }

        if (blank($data['set_code'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'set_code' => 'A set code is required for exams inside an assessment group.',
            ]);
        }

        $duplicate = Exam::where('assessment_group_id', $group->id)
            ->where('set_code', $data['set_code'])
            ->when($exam, fn ($q) => $q->whereKeyNot($exam->id))
            ->exists();

        if ($duplicate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'set_code' => 'This set code is already used in the selected assessment group.',
            ]);
        }

        return $data;
    }

    public function store(
        Request $request,
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
    ) {
        $data = $this->validateGroupSelection($request, $this->validated($request));

        if (!$data['assessment_group_id']
            && !$scoreSync->criterionExistsForVacancy((int)$data['vacancy_id'], $data['assessment_score_key'] ?? null)) {
            return back()->withInput()->withErrors([
                'assessment_score_key'=>'The selected applicant-score criterion does not belong to this vacancy template.',
            ]);
        }
        $requestedPublish = (int) $data['status'] === 1;
        $data['status'] = 0;
        $data['created_by'] = auth()->id();
        $data['approval_status'] = $this->currentUserIsAdmin() ? 'approved' : 'pending';
        $data['approved_by'] = $this->currentUserIsAdmin() ? auth()->id() : null;
        $data['approved_at'] = $this->currentUserIsAdmin() ? now() : null;
        $data['approval_notes'] = $this->currentUserIsAdmin() ? 'Auto-approved because the creator is an administrator.' : null;
        $data['enrollment_key'] = strtoupper(Str::random(8));
        $data['code'] = $data['code'] ?: 'WE-' . now()->format('Ymd-His');

        $exam = Exam::create($data);

        $governance->log('exam_created', [
            'assessment_group_id' => $exam->assessment_group_id,
            'exam_id' => $exam->id,
        ]);

        $message = 'Written exam saved as a draft.';
        if ($requestedPublish) {
            $message .= ' Add/review the items and publish only after readiness validation.';
        }

        return redirect()->route('admin.assessments.edit', $exam)
            ->with('status', $message);
    }

    public function update(
        Request $request,
        Exam $exam,
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
    ) {
        $this->ensureNotArchived($exam);
        if ($exam->attempts()->whereNotNull('started_at')->exists()) {
            return back()->with('status', 'Exam settings are locked after an attempt has started. Create a new governed set/version instead.');
        }

        // A published set may be edited only when this same submission
        // explicitly returns it to draft. Previously, the guard ran before
        // validation and created a catch-22: choosing Draft in the edit form
        // could never be saved because the persisted status was still Published.
        if ((int) $exam->status === 1 && (int) $request->input('status', 1) !== 0) {
            return back()->with('status', 'Return this published set to draft before changing its settings.');
        }

        $data = $this->validateGroupSelection($request, $this->validated($request), $exam);

        if (!$data['assessment_group_id']
            && !$scoreSync->criterionExistsForVacancy((int)$data['vacancy_id'], $data['assessment_score_key'] ?? null)) {
            return back()->withInput()->withErrors([
                'assessment_score_key'=>'The selected applicant-score criterion does not belong to this vacancy template.',
            ]);
        }
        $requestedPublish = (int) $data['status'] === 1;
        $data['status'] = 0;

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

        $exam->update($data);

        if ($requestedPublish) {
            $readiness = $governance->readiness($exam->fresh());

            if (!$readiness['ready']) {
                return back()->with(
                    'status',
                    'Settings saved, but the set remains draft: '.implode(' ', array_slice($readiness['issues'], 0, 8))
                );
            }

            $exam->update(['status' => 1]);
            $governance->recordWrittenExposure($exam->fresh());
            $governance->snapshotExam($exam->fresh(),'published');
            $governance->log('exam_published', [
                'assessment_group_id' => $exam->assessment_group_id,
                'exam_id' => $exam->id,
            ]);

            return redirect()->route('admin.assessments.index')
                ->with('status', 'Written exam updated and published after readiness validation.');
        }

        $governance->log('exam_updated', [
            'assessment_group_id' => $exam->assessment_group_id,
            'exam_id' => $exam->id,
        ]);

        return redirect()->route('admin.assessments.index')
            ->with('status', 'Written exam settings updated.');
    }

    public function duplicate(Exam $exam, AssessmentGovernanceService $governance)
    {
        $exam->load('writtenExams.options');

        $copy = DB::transaction(function () use ($exam) {
            $copy = $exam->replicate();
            $copy->title = $exam->title . ' - Copy';
            $copy->code = ($exam->code ?: 'WE-' . $exam->id) . '-COPY-' . now()->format('His');
            $copy->enrollment_key = strtoupper(Str::random(8));
            // Generic duplication creates a standalone draft. Use the Assessment
            // Group "Add Equivalent Set" workflow for governed parallel sets.
            $copy->assessment_group_id = null;
            $copy->set_code = null;
            $copy->created_by = auth()->id();
            $copy->approval_status = $this->currentUserIsAdmin() ? 'approved' : 'pending';
            $copy->approved_by = $this->currentUserIsAdmin() ? auth()->id() : null;
            $copy->approved_at = $this->currentUserIsAdmin() ? now() : null;
            $copy->approval_notes = $this->currentUserIsAdmin() ? 'Auto-approved because the creator is an administrator.' : null;
            $copy->status = 0;
            $copy->start_date = null;
            $copy->end_date = null;
            $copy->save();

            foreach ($exam->writtenExams as $item) {
                $newItem = $item->replicate();
                $newItem->exam_id = $copy->id;
                $newItem->enrollment_key = $copy->enrollment_key;
                $newItem->save();

                foreach ($item->options as $option) {
                    $newOption = $option->replicate();
                    $newOption->written_exam_id = $newItem->id;
                    $newOption->save();
                }
            }

            return $copy;
        });

        $governance->log('exam_duplicated', [
            'assessment_group_id' => $exam->assessment_group_id,
            'exam_id' => $copy->id,
        ], ['source_exam_id' => $exam->id]);

        return redirect()->route('admin.assessments.edit', $copy)
            ->with('status', 'Exam duplicated as a standalone draft. Use Add Equivalent Set for governed parallel sets.');
    }


    public function generateAi(
        Request $request,
        Exam $exam,
        AssessmentAiService $ai,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureNotArchived($exam);
        if ((int) $exam->status === 1) {
            return back()->with('status', 'Return the set to draft before generating or changing items.');
        }

        if ($exam->attempts()->whereNotNull('started_at')->exists()) {
            return back()->with('status', 'Cannot generate items after attempts exist. Duplicate the exam as a new set.');
        }

        $data = $request->validate([
            'count' => 'required|integer|min:1|max:100',
            'solo_unistructural' => 'required|integer|min:0|max:100',
            'solo_multistructural' => 'required|integer|min:0|max:100',
            'solo_relational' => 'required|integer|min:0|max:100',
            'solo_extended_abstract' => 'required|integer|min:0|max:100',
            'additional_context' => 'nullable|string|max:30000',
            'generation_focus' => 'required|in:mixed,duties,technical,situational',
        ]);

        $distribution = [
            'unistructural' => $data['solo_unistructural'],
            'multistructural' => $data['solo_multistructural'],
            'relational' => $data['solo_relational'],
            'extended_abstract' => $data['solo_extended_abstract'],
        ];

        if (array_sum($distribution) !== 100) {
            return back()->withInput()->with('status', 'SOLO distribution must total 100%.');
        }

        $contextOptions = [
            'use_qualifications' => $request->boolean('use_qualifications'),
            'use_job_description' => $request->boolean('use_job_description'),
            'additional_context' => trim((string) ($data['additional_context'] ?? '')),
            'generation_focus' => $data['generation_focus'],
        ];

        if (!$contextOptions['use_qualifications']
            && !$contextOptions['use_job_description']
            && $contextOptions['additional_context'] === '') {
            return back()->withInput()->with(
                'status',
                'Select at least one vacancy context source or paste additional context before generating.'
            );
        }

        if ($exam->assessment_group_id) {
            $exam->loadMissing('assessmentGroup');
            if ($exam->assessmentGroup?->blueprint) {
                $contextOptions['blueprint'] = $exam->assessmentGroup->blueprint;
            }

            $contextOptions['avoid_questions'] = WrittenExam::query()
                ->whereHas('exam', fn ($q) => $q->where('assessment_group_id', $exam->assessment_group_id))
                ->orderByDesc('id')
                ->limit(250)
                ->pluck('question')
                ->all();
        }

        // A fresh AI generation replaces the current draft item set. Prior
        // queued/processing runs are superseded so stale jobs cannot repopulate
        // the exam after the administrator intentionally regenerates it.
        DB::transaction(function () use ($exam) {
            AssessmentAiGenerationRun::where('exam_id',$exam->id)
                ->whereIn('status',['queued','processing'])
                ->update([
                    'status'=>'superseded',
                    'last_error'=>'Superseded by a newer AI generation request.',
                ]);

            $exam->writtenExams()->delete();
        });

        // All AI generation is queued so model latency never occupies a web request.
        if ((int) $data['count'] >= 1) {
            $exam->update([
                'ai_context' => $contextOptions['additional_context'] ?: null,
                'ai_generation_focus' => $contextOptions['generation_focus'],
                'ai_use_qualifications' => $contextOptions['use_qualifications'],
                'ai_use_job_description' => $contextOptions['use_job_description'],
            ]);

            $batchSize = 10;
            $batchCount = (int) ceil(((int) $data['count']) / $batchSize);

            $run = AssessmentAiGenerationRun::create([
                'exam_id' => $exam->id,
                'requested_count' => (int) $data['count'],
                'generated_count' => 0,
                'failed_batches' => 0,
                'batch_count' => $batchCount,
                'completed_batches' => 0,
                'solo_distribution' => $distribution,
                'context_options' => collect($contextOptions)->except('avoid_questions')->all(),
                'status' => 'queued',
                'requested_by' => auth()->id(),
            ]);

            $remaining = (int) $data['count'];
            while ($remaining > 0) {
                $size = min($batchSize, $remaining);
                GenerateWrittenExamItemsBatch::dispatch($run->id, $size);
                $remaining -= $size;
            }

            $governance->log('ai_generation_queued', [
                'assessment_group_id' => $exam->assessment_group_id,
                'exam_id' => $exam->id,
            ], [
                'run_id' => $run->id,
                'requested_count' => (int) $data['count'],
                'batch_count' => $batchCount,
            ]);

            return back()->with(
                'status',
                "Current draft items were cleared. AI generation queued in {$batchCount} batch(es); only the new generation will populate this test."
            );
        }

        try {
            $exam->update([
                'ai_context' => $contextOptions['additional_context'] ?: null,
                'ai_generation_focus' => $contextOptions['generation_focus'],
                'ai_use_qualifications' => $contextOptions['use_qualifications'],
                'ai_use_job_description' => $contextOptions['use_job_description'],
            ]);

            $items = $ai->generateWrittenItems(
                $exam->vacancy,
                (int)$data['count'],
                $distribution,
                $contextOptions
            );

            $created = DB::transaction(function () use ($exam, $items) {
                $created = 0;

                foreach ($items as $generated) {
                    if (!isset($generated['question'], $generated['options'], $generated['correct_index'])
                        || count($generated['options']) !== 4
                        || !in_array((int)$generated['correct_index'], [0,1,2,3], true)) {
                        continue;
                    }

                    $letters = ['A','B','C','D'];
                    $answerKey = $letters[(int)$generated['correct_index']];

                    $item = WrittenExam::create([
                        'exam_id' => $exam->id,
                        'enrollment_key' => $exam->enrollment_key,
                        'question' => $generated['question'],
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
                            'is_correct' => $index === (int)$generated['correct_index'],
                            'source_position' => $index + 1,
                        ]);
                    }

                    $created++;
                }

                return $created;
            });

            if ($created === 0) {
                throw new \RuntimeException('The AI response contained no valid assessment items.');
            }

            $governance->log('ai_items_generated', [
                'assessment_group_id' => $exam->assessment_group_id,
                'exam_id' => $exam->id,
            ], ['created' => $created]);

            $message = "{$created} AI-generated item(s) were added for review.";
            if ($created < (int) $data['count']) {
                $message .= " Requested {$data['count']}; some returned items were invalid and were skipped.";
            }

            return back()->with('status', $message);
        } catch (\Throwable $e) {
            Log::error('Written assessment AI generation failed', [
                'exam_id' => $exam->id,
                'vacancy_id' => $exam->vacancy_id,
                'exception' => $e,
            ]);

            return back()->withInput()->with(
                'status',
                'AI generation failed. No generated items were saved. Please review the context and try again.'
            );
        }
    }


    public function assignApplicants(
        Request $request,
        Exam $exam,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureNotArchived($exam);
        $data = $request->validate(['application_codes' => 'required|string']);
        $codes = collect(preg_split('/[\s,;]+/', $data['application_codes']))
            ->map(fn($x) => trim($x))->filter()->unique()->values();

        $applications = Application::where('vacancy_id', $exam->vacancy_id)
            ->whereIn('application_code', $codes)
            ->whereHas('assessment')
            ->get(['id','application_code']);

        $conflictingApplicationIds = collect();

        if ($exam->assessment_group_id && $applications->isNotEmpty()) {
            $siblingExamIds = Exam::where('assessment_group_id', $exam->assessment_group_id)
                ->whereKeyNot($exam->id)
                ->pluck('id');

            if ($siblingExamIds->isNotEmpty()) {
                $conflictingApplicationIds = ExamAssignment::whereIn('exam_id', $siblingExamIds)
                    ->whereIn('application_id', $applications->pluck('id'))
                    ->pluck('application_id')
                    ->unique();
            }
        }

        $eligibleApplications = $applications
            ->reject(fn ($application) => $conflictingApplicationIds->contains($application->id))
            ->values();

        $assigned = 0;
        $now = now();

        foreach ($eligibleApplications->chunk(500) as $chunk) {
            $rows = $chunk->map(fn ($application) => [
                'exam_id' => $exam->id,
                'application_id' => $application->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            $assigned += ExamAssignment::query()->insertOrIgnore($rows);
        }

        $conflicts = $conflictingApplicationIds->count();
        $alreadyOnThisSet = max(0, $eligibleApplications->count() - $assigned);

        $message = "{$assigned} applicant assignment(s) added.";
        if ($alreadyOnThisSet) {
            $message .= " {$alreadyOnThisSet} were already assigned to this set.";
        }
        if ($conflicts) {
            $message .= " {$conflicts} skipped because they are assigned to another set in this assessment group.";
        }

        $governance->log('applicants_assigned', [
            'assessment_group_id' => $exam->assessment_group_id,
            'exam_id' => $exam->id,
        ], [
            'added' => $assigned,
            'already_assigned' => $alreadyOnThisSet,
            'conflicts' => $conflicts,
        ]);

        return back()->with('status', $message);
    }

    public function destroy(Exam $exam)
    {
        abort_unless($this->currentUserIsAdmin(), 403);

        if ($exam->attempts()->exists()) {
            return back()->with('status', 'Cannot delete a Written Test after any applicant has attempted it. Archive it instead.');
        }

        if (AssessmentGroupAttemptLock::where('exam_id',$exam->id)->exists()) {
            return back()->with('status', 'Cannot delete this set because an applicant is already locked to it.');
        }

        $group = $exam->assessmentGroup;

        if ($group && $group->exams()->count() <= 1) {
            return back()->with('status', 'This is the last set in the group. Delete the Written Assessment Group instead.');
        }

        DB::transaction(function () use ($exam,$group) {
            $exam->delete();

            if ($group) {
                $group->update([
                    'expected_sets'=>$group->exams()->count(),
                ]);
            }
        });

        return redirect()->route('admin.assessments.index')
            ->with('status', 'Written Test deleted. No applicant attempts were affected.');
    }

    public function approve(
        Request $request,
        Exam $exam,
        AssessmentGovernanceService $governance
    ) {
        $this->ensureNotArchived($exam);
        abort_unless($this->currentUserIsAdmin(), 403);

        $data = $request->validate([
            'approval_notes'=>'nullable|string|max:5000',
        ]);

        $exam->update([
            'approval_status'=>'approved',
            'approved_by'=>auth()->id(),
            'approved_at'=>now(),
            'approval_notes'=>$data['approval_notes'] ?? 'Approved by administrator.',
        ]);

        $governance->log('exam_approved', [
            'assessment_group_id'=>$exam->assessment_group_id,
            'exam_id'=>$exam->id,
        ], [
            'creator_id'=>$exam->created_by,
        ]);

        return back()->with('status', 'Written test approved.');
    }

    public function toggleStatus(Exam $exam, AssessmentGovernanceService $governance)
    {
        $this->ensureNotArchived($exam);
        if ((int) $exam->status !== 1) {
            $readiness = $governance->readiness($exam);

            if (!$readiness['ready']) {
                return back()->with(
                    'status',
                    'Cannot publish this set yet: '.implode(' ', array_slice($readiness['issues'], 0, 8))
                );
            }
        }

        $exam->status = $exam->status == 1 ? 0 : 1;
        $exam->save();

        if ((int)$exam->status === 1) {
            $governance->recordWrittenExposure($exam->fresh());
            $governance->snapshotExam($exam->fresh(),'published');
        }

        $governance->log(
            $exam->status ? 'exam_published' : 'exam_unpublished',
            [
                'assessment_group_id' => $exam->assessment_group_id,
                'exam_id' => $exam->id,
            ]
        );

        return back()->with(
            'status',
            $exam->status ? 'Written exam published after readiness validation.' : 'Written exam returned to draft.'
        );
    }

    public function pause(
        Request $request,
        Exam $exam,
        AssessmentGovernanceService $governance
    ) {
        $data = $request->validate(['reason'=>'required|string|max:3000']);

        if ($exam->archived_at) {
            return back()->with('status', 'Archived written tests cannot be paused.');
        }

        $exam->update([
            'is_paused'=>true,
            'pause_reason'=>$data['reason'],
            'paused_at'=>now(),
            'paused_by'=>auth()->id(),
        ]);

        $governance->log('exam_paused', [
            'assessment_group_id'=>$exam->assessment_group_id,
            'exam_id'=>$exam->id,
        ], ['reason'=>$data['reason'], 'scope'=>'new_starts']);

        return back()->with('status', 'New starts are paused. Existing in-progress attempts may continue.');
    }

    public function resume(
        Exam $exam,
        AssessmentGovernanceService $governance
    ) {
        $exam->update([
            'is_paused'=>false,
            'pause_reason'=>null,
            'paused_at'=>null,
            'paused_by'=>null,
        ]);

        $governance->log('exam_resumed', [
            'assessment_group_id'=>$exam->assessment_group_id,
            'exam_id'=>$exam->id,
        ]);

        return back()->with('status', 'Written test resumed for new starts.');
    }

    public function archive(
        Exam $exam,
        AssessmentGovernanceService $governance
    ) {
        if ($exam->attempts()->where('status',1)->exists()) {
            return back()->with('status', 'Cannot archive while attempts are in progress.');
        }

        $exam->update([
            'status'=>0,
            'is_paused'=>false,
            'archived_at'=>now(),
            'archived_by'=>auth()->id(),
        ]);

        $governance->snapshotExam($exam->fresh(),'archived');

        $governance->log('exam_archived', [
            'assessment_group_id'=>$exam->assessment_group_id,
            'exam_id'=>$exam->id,
        ]);

        return redirect()->route('admin.assessment_center.index')
            ->with('status', 'Written test archived and frozen.');
    }

    public function preview(Exam $exam, AssessmentGovernanceService $governance)
    {
        $exam->load(['vacancy','assessmentGroup','writtenExams.options']);
        $readiness = $governance->readiness($exam);
        $infrastructure = $governance->infrastructureReadiness();

        return view('admin.assessments.preview', compact('exam','readiness','infrastructure'));
    }

    public function results(Exam $exam)
    {
        $exam->load(['writtenExams.options']);
        $attempts = $exam->attempts()
            ->with(['application', 'answers.selectedOption', 'answers.item', 'itemOrders', 'events'])
            ->where('status', 2)
            ->paginate(25)
            ->withQueryString();

        return view('admin.assessments.results', compact('exam', 'attempts'));
    }

    public function destroyAttempt(Exam $exam, ExamAttempt $attempt)
    {
        if ((int) $attempt->exam_id !== (int) $exam->id) abort(404);

        if ($attempt->started_at) {
            return back()->with(
                'status',
                'Started attempts are retained for audit integrity. Use the assessment-group void/retake workflow instead.'
            );
        }

        $attempt->delete();

        return back()->with('status', 'Unused attempt record deleted.');
    }
}
