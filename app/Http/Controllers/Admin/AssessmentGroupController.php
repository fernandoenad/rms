<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AssessmentGroup;
use App\Models\AssessmentGroupAttemptLock;
use App\Models\AssessmentIncident;
use App\Models\Exam;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\Vacancy;
use App\Services\AssessmentAnalyticsService;
use App\Services\AssessmentGovernanceService;
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

    public function index()
    {
        $groups = AssessmentGroup::with('vacancy:id,position_title')
            ->withCount(['exams', 'attemptLocks'])
            ->orderByDesc('id')
            ->get();

        return view('admin.assessment_groups.index', compact('groups'));
    }

    public function create()
    {
        $vacancies = Vacancy::orderByDesc('cycle')
            ->orderBy('position_title')
            ->get(['id', 'position_title', 'cycle']);

        return view('admin.assessment_groups.create', compact('vacancies'));
    }

    protected function validated(Request $request, ?AssessmentGroup $group = null): array
    {
        return $request->validate([
            'vacancy_id' => 'required|exists:vacancies,id',
            'title' => 'required|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('assessment_groups', 'code')
                    ->ignore($group?->id)
                    ->where(fn ($q) => $q->where('vacancy_id', $request->vacancy_id)),
            ],
            'expected_sets' => 'required|integer|min:1|max:26',
            'default_duration' => 'nullable|integer|min:1|max:480',
            'status' => 'required|boolean',
            'score_release_policy' => 'required|in:hidden,manual,after_close,immediate',
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

    public function store(Request $request, AssessmentGovernanceService $governance)
    {
        $data = $this->validated($request);
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

    public function edit(AssessmentGroup $assessmentGroup, AssessmentGovernanceService $governance)
    {
        $vacancies = Vacancy::orderByDesc('cycle')
            ->orderBy('position_title')
            ->get(['id', 'position_title', 'cycle']);

        $assessmentGroup->load([
            'exams' => fn ($q) => $q->withCount(['attempts', 'assignments'])->orderBy('set_code'),
            'incidents' => fn ($q) => $q->where('status', 'open')->latest()->limit(20),
        ]);

        $readiness = $assessmentGroup->exams
            ->mapWithKeys(fn ($exam) => [$exam->id => $governance->readiness($exam)]);

        return view('admin.assessment_groups.edit', compact(
            'assessmentGroup',
            'vacancies',
            'readiness'
        ));
    }

    public function update(
        Request $request,
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance
    ) {
        $data = $this->validated($request, $assessmentGroup);
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

    public function createEquivalentSet(
        Request $request,
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance
    ) {
        if ($assessmentGroup->exams()->whereHas('attempts')->exists()) {
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
        $assessmentGroup->load('vacancy:id,position_title');

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
        AssessmentGovernanceService $governance
    ) {
        if ($assessmentGroup->score_release_policy === 'hidden') {
            return back()->with('status', 'Score release is disabled for this assessment group.');
        }

        $assessmentGroup->update(['scores_released_at' => now()]);
        $governance->log('scores_released', [
            'assessment_group_id' => $assessmentGroup->id,
        ]);

        return back()->with('status', 'Applicant score visibility has been released.');
    }

    public function hideScores(
        AssessmentGroup $assessmentGroup,
        AssessmentGovernanceService $governance
    ) {
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
                            optional($attempt->started_at)?->toIso8601String(),
                            optional($attempt->ended_at)?->toIso8601String(),
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
