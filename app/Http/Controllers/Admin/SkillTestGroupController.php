<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SkillTest;
use App\Jobs\GenerateSkillTestSetTask;
use App\Models\SkillTestGroup;
use App\Models\SkillTestAttempt;
use App\Models\Vacancy;
use App\Services\AssessmentGovernanceService;
use App\Services\AssessmentScoreSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SkillTestGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function currentUserIsAdmin(): bool
    {
        return (int) optional(optional(auth()->user())->role)->level === 1;
    }

    protected function nextSetCode(SkillTestGroup $group): string
    {
        $used = $group->skillTests()
            ->pluck('set_code')
            ->filter()
            ->map(fn ($x) => strtoupper((string) $x));

        for ($i = 0; $i < 26; $i++) {
            $code = chr(65 + $i);
            if (!$used->contains($code)) return $code;
        }

        return 'SET-'.($used->count() + 1);
    }

    public function index()
    {
        $groups = SkillTestGroup::with('vacancy:id,position_title')
            ->withCount(['skillTests','attemptLocks'])
            ->orderByDesc('id')
            ->get();

        return view('admin.skill_groups.index', compact('groups'));
    }

    public function create(AssessmentScoreSyncService $scoreSync)
    {
        $vacancies = Vacancy::orderByDesc('id')
            ->get(['id','position_title','cycle','template_id']);

        $scoreCriteriaByVacancy = $vacancies->mapWithKeys(
            fn ($vacancy) => [$vacancy->id => $scoreSync->criteriaForVacancy($vacancy)]
        );

        return view('admin.skill_groups.create', compact('vacancies','scoreCriteriaByVacancy'));
    }

    protected function validated(Request $request, ?SkillTestGroup $group = null): array
    {
        $codeRule = Rule::unique('skill_test_groups','code')
            ->where(fn ($q) => $q->where('vacancy_id',$request->vacancy_id));

        if ($group) $codeRule->ignore($group->id);

        return $request->validate([
            'vacancy_id'=>'required|exists:vacancies,id',
            'title'=>'required|string|max:255',
            'code'=>['nullable','string','max:100',$codeRule],
            'expected_sets'=>'required|integer|min:1|max:26',
            'duration'=>'required|integer|min:1|max:480',
            'access_mode'=>'required|in:all_taken_in,selected_applicants',
            'submission_modes'=>'required|array|min:1',
            'submission_modes.*'=>'in:inline,file',
            'allowed_extensions'=>'nullable|string|max:255',
            'max_file_size_kb'=>'required|integer|min:100|max:51200',
            'ai_scoring'=>'required|boolean',
            'score_release_policy'=>'required|in:hidden,manual,after_close,immediate',
            'assessment_score_key'=>'nullable|string|max:255',
            'status'=>'required|boolean',
        ]);
    }

    protected function commonSetData(array $data, SkillTestGroup $group): array
    {
        $extensions = collect(explode(',', $data['allowed_extensions'] ?? 'docx'))
            ->map(fn ($x) => strtolower(trim($x)))
            ->filter()->unique()->values()->all();

        return [
            'vacancy_id'=>$group->vacancy_id,
            'skill_test_group_id'=>$group->id,
            'created_by'=>auth()->id(),
            'start_date'=>null,
            'end_date'=>null,
            'duration'=>$data['duration'],
            'access_mode'=>$data['access_mode'],
            'submission_modes'=>$data['submission_modes'],
            'allowed_extensions'=>$extensions,
            'max_file_size_kb'=>$data['max_file_size_kb'],
            'ai_scoring'=>(bool)$data['ai_scoring'],
            // Group controls official score release/mapping.
            'score_release_policy'=>$group->score_release_policy,
            'assessment_score_key'=>null,
            'status'=>0,
            'approval_status'=>$this->currentUserIsAdmin() ? 'approved' : 'pending',
            'approved_by'=>$this->currentUserIsAdmin() ? auth()->id() : null,
            'approved_at'=>$this->currentUserIsAdmin() ? now() : null,
            'approval_notes'=>$this->currentUserIsAdmin()
                ? 'Auto-approved because the creator is an administrator.'
                : null,
            'review_status'=>'pending_review',
            'review_notes'=>'Equivalent-set placeholder requires task and rubric review before publication.',
        ];
    }

    protected function createSetPlaceholder(SkillTestGroup $group, array $data): SkillTest
    {
        $setCode = $this->nextSetCode($group);
        $common = $this->commonSetData($data,$group);

        return SkillTest::create(array_merge($common, [
            'set_code'=>$setCode,
            'title'=>$group->title.' - Set '.$setCode,
            'code'=>($group->code ?: 'SG-'.$group->id).'-'.$setCode,
            'instructions'=>'Draft placeholder. Generate or enter an equivalent skills task before publishing.',
            'expected_output'=>null,
        ]));
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

        $group = DB::transaction(function () use ($data,$governance) {
            $group = SkillTestGroup::create([
                'vacancy_id'=>$data['vacancy_id'],
                'title'=>$data['title'],
                'code'=>$data['code'] ?: 'SG-'.now()->format('Ymd-His'),
                'expected_sets'=>$data['expected_sets'],
                'status'=>(bool)$data['status'],
                'score_release_policy'=>$data['score_release_policy'],
                'assessment_score_key'=>$data['assessment_score_key'] ?? null,
            ]);

            for ($i=0; $i<(int)$data['expected_sets']; $i++) {
                $set = $this->createSetPlaceholder($group,$data);
                $governance->log('skill_set_placeholder_created', [
                    'skill_test_id'=>$set->id,
                ], [
                    'skill_test_group_id'=>$group->id,
                    'set_code'=>$set->set_code,
                ]);
            }

            return $group;
        });

        return redirect()->route('admin.skill_groups.edit',$group)
            ->with('status','Skills Test group created with '.(int)$data['expected_sets'].' draft equivalent set(s). Set the schedule for each set, configure its task/rubric, review, then publish.');
    }

    public function edit(
        SkillTestGroup $skillTestGroup,
        AssessmentGovernanceService $governance,
        AssessmentScoreSyncService $scoreSync
    ) {
        $skillTestGroup->load([
            'vacancy',
            'skillTests'=>fn ($q) => $q->withCount(['attempts','assignments'])->orderBy('set_code'),
        ]);

        $scoreCriteria = $scoreSync->criteriaForVacancy($skillTestGroup->vacancy);
        $readiness = $skillTestGroup->skillTests->mapWithKeys(
            fn ($test) => [$test->id=>$governance->skillReadiness($test)]
        );

        return view('admin.skill_groups.edit', compact(
            'skillTestGroup','scoreCriteria','readiness'
        ));
    }

    public function update(
        Request $request,
        SkillTestGroup $skillTestGroup,
        AssessmentScoreSyncService $scoreSync
    ) {
        abort_if($skillTestGroup->archived_at,403,'This Skills Test group is archived and frozen.');

        $data = $this->validated($request,$skillTestGroup);

        if ((int)$data['vacancy_id'] !== (int)$skillTestGroup->vacancy_id) {
            return back()->withInput()->withErrors([
                'vacancy_id'=>'The position cannot be changed after Skills Test sets have been created.',
            ]);
        }

        if (!$scoreSync->criterionExistsForVacancy((int)$data['vacancy_id'], $data['assessment_score_key'] ?? null)) {
            return back()->withInput()->withErrors([
                'assessment_score_key'=>'The selected applicant-score criterion does not belong to this vacancy template.',
            ]);
        }

        $started = $skillTestGroup->skillTests()
            ->whereHas('attempts',fn ($q)=>$q->whereNotNull('started_at'))
            ->exists();

        $existing = $skillTestGroup->skillTests()->count();
        if ($started && (int)$data['expected_sets'] !== (int)$skillTestGroup->expected_sets) {
            return back()->withInput()->withErrors([
                'expected_sets'=>'The number of equivalent sets is locked after an applicant starts.',
            ]);
        }

        if ((int)$data['expected_sets'] < $existing) {
            return back()->withInput()->withErrors([
                'expected_sets'=>"This group already has {$existing} set(s). Existing sets are never silently deleted.",
            ]);
        }

        DB::transaction(function () use ($data,$skillTestGroup,$existing) {
            $mappingChanged = ($data['assessment_score_key'] ?? null) !== $skillTestGroup->assessment_score_key
                || $data['score_release_policy'] !== $skillTestGroup->score_release_policy;

            $skillTestGroup->update([
                'title'=>$data['title'],
                'code'=>$data['code'],
                'expected_sets'=>$data['expected_sets'],
                'status'=>(bool)$data['status'],
                'score_release_policy'=>$data['score_release_policy'],
                'assessment_score_key'=>$data['assessment_score_key'] ?? null,
                'scores_synced_at'=>$mappingChanged ? null : $skillTestGroup->scores_synced_at,
                'scores_released_at'=>$data['score_release_policy']==='manual'
                    ? $skillTestGroup->scores_released_at
                    : null,
            ]);

            $skillTestGroup->skillTests()
                ->whereDoesntHave('attempts',fn($q)=>$q->whereNotNull('started_at'))
                ->update([
                    'duration'=>$data['duration'],
                    'access_mode'=>$data['access_mode'],
                    'submission_modes'=>json_encode(array_values($data['submission_modes'])),
                    'allowed_extensions'=>json_encode(
                        collect(explode(',',$data['allowed_extensions'] ?? 'docx'))
                            ->map(fn($x)=>strtolower(trim($x)))->filter()->unique()->values()->all()
                    ),
                    'max_file_size_kb'=>$data['max_file_size_kb'],
                    'ai_scoring'=>(bool)$data['ai_scoring'],
                    'score_release_policy'=>$data['score_release_policy'],
                ]);

            for ($i=$existing; $i<(int)$data['expected_sets']; $i++) {
                $this->createSetPlaceholder($skillTestGroup,$data);
            }
        });

        return back()->with('status','Skills Test group settings updated.');
    }

    public function generateAllSets(
        Request $request,
        SkillTestGroup $skillTestGroup
    ) {
        abort_if($skillTestGroup->archived_at,403,'This Skills Test group is archived and frozen.');

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
            return back()->with('status','Select at least one vacancy context source or provide additional context.');
        }

        $sets = $skillTestGroup->skillTests()
            ->where('status',0)
            ->whereDoesntHave('attempts',fn($q)=>$q->whereNotNull('started_at'))
            ->withCount('rubricCriteria')
            ->orderBy('set_code')
            ->get();

        $targets = $sets->filter(function($set){
            $placeholder = str_starts_with((string)$set->instructions,'Draft placeholder');
            return $placeholder || (int)$set->rubric_criteria_count === 0;
        })->values();

        if ($targets->isEmpty()) {
            return back()->with(
                'status',
                'No empty draft Skills sets are available. Existing generated/manual tasks were left untouched.'
            );
        }

        $source = $targets->first();
        $jobs = [];

        foreach ($targets as $index=>$set) {
            $set->update([
                'review_status'=>'pending_review',
                'review_notes'=>'AI generation queued...',
                'ai_context'=>$contextOptions['additional_context'] ?: null,
                'ai_generation_focus'=>$contextOptions['generation_focus'],
                'ai_use_qualifications'=>$contextOptions['use_qualifications'],
                'ai_use_job_description'=>$contextOptions['use_job_description'],
            ]);

            $jobs[] = new GenerateSkillTestSetTask(
                $set->id,
                $contextOptions,
                $index === 0 ? null : $source->id
            );
        }

        Bus::chain($jobs)->onQueue('assessment-ai')->dispatch();

        return back()->with(
            'status',
            'AI generation queued for '.$targets->count().' Skills Test set(s). Set '.$source->set_code.' will establish the shared rubric; the remaining sets will receive distinct but equivalent tasks using that same rubric. Review and approve each set before publishing.'
        );
    }

    public function destroy(SkillTestGroup $skillTestGroup)
    {
        abort_unless($this->currentUserIsAdmin(), 403);

        if ($skillTestGroup->skillTests()->whereHas('attempts')->exists()
            || $skillTestGroup->attemptLocks()->exists()) {
            return back()->with(
                'status',
                'Cannot delete this Skills Test Group because an applicant has already started or been locked to one of its sets. Archive it instead.'
            );
        }

        DB::transaction(function () use ($skillTestGroup) {
            // Sets use nullOnDelete for the group FK, so delete them explicitly.
            $skillTestGroup->skillTests()->get()->each->delete();
            $skillTestGroup->delete();
        });

        return redirect()->route('admin.skill_groups.index')
            ->with('status','Skills Test Group and all of its unattempted sets were deleted.');
    }

    public function results(SkillTestGroup $skillTestGroup)
    {
        $skillTestGroup->load([
            'vacancy:id,position_title',
            'skillTests' => fn ($q) => $q->orderBy('set_code'),
        ]);

        $setIds = $skillTestGroup->skillTests->pluck('id');

        $dashboard = SkillTestAttempt::query()
            ->whereIn('skill_test_id', $setIds)
            ->whereNotNull('started_at')
            ->selectRaw('COUNT(*) as attempted')
            ->selectRaw('SUM(CASE WHEN status = 1 AND submitted_at IS NULL AND expires_at IS NOT NULL AND expires_at > ? THEN 1 ELSE 0 END) as taking_now', [now()])
            ->selectRaw('SUM(CASE WHEN status = 1 AND submitted_at IS NULL AND expires_at IS NOT NULL AND expires_at <= ? THEN 1 ELSE 0 END) as awaiting_timeout', [now()])
            ->selectRaw('SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as submitted')
            ->selectRaw('SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as voided')
            ->selectRaw('SUM(CASE WHEN status = 2 AND final_score IS NOT NULL THEN 1 ELSE 0 END) as evaluated')
            ->selectRaw('AVG(CASE WHEN status = 2 AND final_score IS NOT NULL THEN final_score END) as mean_final_score')
            ->first();

        $dashboard->completion_rate = (int) $dashboard->attempted > 0
            ? round(((int) $dashboard->submitted / (int) $dashboard->attempted) * 100, 1)
            : 0;

        $setSummary = $skillTestGroup->skillTests->map(function ($set) {
            $row = $set->attempts()
                ->whereNotNull('started_at')
                ->selectRaw('COUNT(*) as attempted')
                ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as in_progress')
                ->selectRaw('SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as submitted')
                ->selectRaw('SUM(CASE WHEN status = 2 AND final_score IS NOT NULL THEN 1 ELSE 0 END) as evaluated')
                ->selectRaw('AVG(CASE WHEN status = 2 AND final_score IS NOT NULL THEN final_score END) as mean_score')
                ->first();

            return [
                'id' => $set->id,
                'set_code' => $set->set_code,
                'title' => $set->title,
                'attempted' => (int) ($row->attempted ?? 0),
                'in_progress' => (int) ($row->in_progress ?? 0),
                'submitted' => (int) ($row->submitted ?? 0),
                'evaluated' => (int) ($row->evaluated ?? 0),
                'mean_score' => $row->mean_score !== null ? (float) $row->mean_score : null,
            ];
        });

        $attempts = SkillTestAttempt::query()
            ->whereIn('skill_test_id', $setIds)
            ->with([
                'application:id,application_code,first_name,middle_name,last_name',
                'skillTest:id,skill_test_group_id,title,set_code',
                'aiEvaluations' => fn ($q) => $q->latest('id'),
            ])
            ->whereNotNull('started_at')
            ->whereIn('status', [1,2,3])
            ->orderByRaw('CASE WHEN status = 1 THEN 0 WHEN status = 2 THEN 1 ELSE 2 END')
            ->orderByDesc('started_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.skill_groups.results', compact(
            'skillTestGroup',
            'dashboard',
            'setSummary',
            'attempts'
        ));
    }

    public function exportCsv(SkillTestGroup $skillTestGroup)
    {
        $setIds = $skillTestGroup->skillTests()->pluck('id');
        $filename = Str::slug($skillTestGroup->title).'-official-results.csv';

        return response()->streamDownload(function () use ($setIds) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Application Code',
                'Applicant',
                'Set',
                'Started',
                'Submitted',
                'Status',
                'AI Proposed Score',
                'Human Final Score',
                'Evaluated At',
                'Void Reason',
            ]);

            SkillTestAttempt::query()
                ->whereIn('skill_test_id', $setIds)
                ->with([
                    'application:id,application_code,first_name,middle_name,last_name',
                    'skillTest:id,set_code,title',
                ])
                ->whereNotNull('started_at')
                ->orderBy('id')
                ->chunkById(500, function ($attempts) use ($handle) {
                    foreach ($attempts as $attempt) {
                        fputcsv($handle, [
                            optional($attempt->application)->application_code,
                            optional($attempt->application)->getFullname(),
                            optional($attempt->skillTest)->set_code ?: optional($attempt->skillTest)->title,
                            $attempt->started_at?->toIso8601String(),
                            $attempt->submitted_at?->toIso8601String(),
                            match ((int) $attempt->status) {
                                1 => 'In progress',
                                2 => 'Submitted',
                                3 => 'Voided',
                                default => 'Unknown',
                            },
                            $attempt->ai_proposed_score,
                            $attempt->final_score,
                            $attempt->evaluated_at?->toIso8601String(),
                            $attempt->void_reason,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function releaseScores(
        SkillTestGroup $skillTestGroup,
        AssessmentScoreSyncService $scoreSync
    ) {
        if ($skillTestGroup->score_release_policy !== 'manual') {
            return back()->with('status','This group is not using manual score release.');
        }

        $skillTestGroup->update(['scores_released_at'=>now()]);
        $sync = $scoreSync->syncSkillGroup($skillTestGroup->fresh());

        return back()->with(
            'status',
            'Skills Test group scores released. '.$sync['synced'].' applicant score(s) synchronized.'
            .($sync['skipped'] ? ' '.$sync['skipped'].' record(s) were skipped.' : '')
        );
    }

    public function hideScores(SkillTestGroup $skillTestGroup)
    {
        $skillTestGroup->update(['scores_released_at'=>null,'scores_synced_at'=>null]);
        return back()->with('status','Manual Skills Test group score release was withdrawn.');
    }

    public function pause(Request $request, SkillTestGroup $skillTestGroup)
    {
        $data=$request->validate(['reason'=>'required|string|max:3000']);
        $skillTestGroup->update([
            'is_paused'=>true,
            'pause_reason'=>$data['reason'],
            'paused_at'=>now(),
            'paused_by'=>auth()->id(),
        ]);
        return back()->with('status','New starts for all Skills Test sets in this group are paused.');
    }

    public function resume(SkillTestGroup $skillTestGroup)
    {
        $skillTestGroup->update([
            'is_paused'=>false,'pause_reason'=>null,'paused_at'=>null,'paused_by'=>null,
        ]);
        return back()->with('status','Skills Test group resumed.');
    }

    public function archive(SkillTestGroup $skillTestGroup)
    {
        $inProgress = $skillTestGroup->skillTests()
            ->whereHas('attempts',fn($q)=>$q->where('status',1))
            ->exists();

        if ($inProgress) {
            return back()->with('status','Cannot archive while a Skills Test attempt is in progress.');
        }

        $skillTestGroup->update([
            'status'=>false,
            'is_paused'=>false,
            'archived_at'=>now(),
            'archived_by'=>auth()->id(),
        ]);

        $skillTestGroup->skillTests()->update(['status'=>0]);

        return redirect()->route('admin.skill_groups.index')
            ->with('status','Skills Test group archived and frozen.');
    }

    public function addEquivalentSet(
        Request $request,
        SkillTestGroup $skillTestGroup,
        AssessmentGovernanceService $governance
    ) {
        abort_if($skillTestGroup->archived_at,403);

        if ($skillTestGroup->skillTests()->count() >= 26) {
            return back()->with('status','Maximum of 26 equivalent sets reached.');
        }

        $sourceId = $request->integer('source_skill_test_id');
        $source = $sourceId
            ? $skillTestGroup->skillTests()->with('rubricCriteria')->find($sourceId)
            : null;

        $setCode = $this->nextSetCode($skillTestGroup);

        $set = DB::transaction(function () use ($skillTestGroup,$source,$setCode) {
            if ($source) {
                $copy = $source->replicate();
                $copy->set_code = $setCode;
                $copy->title = preg_replace('/\s+-\s+Set\s+\S+$/i','',$skillTestGroup->title).' - Set '.$setCode;
                $copy->code = ($skillTestGroup->code ?: 'SG-'.$skillTestGroup->id).'-'.$setCode;
                $copy->status = 0;
                $copy->start_date = null;
                $copy->end_date = null;
                $copy->created_by = auth()->id();
                $copy->approval_status = $this->currentUserIsAdmin() ? 'approved' : 'pending';
                $copy->approved_by = $this->currentUserIsAdmin() ? auth()->id() : null;
                $copy->approved_at = $this->currentUserIsAdmin() ? now() : null;
                $copy->review_status = 'pending_review';
                $copy->reviewed_by = null;
                $copy->reviewed_at = null;
                $copy->review_notes = 'Equivalent set copied from another task and requires independent review.';
                $copy->scores_released_at = null;
                $copy->scores_synced_at = null;
                $copy->supersedes_skill_test_id = null;
                $copy->task_version = 1;
                $copy->save();

                foreach ($source->rubricCriteria as $criterion) {
                    $copy->allRubricCriteria()->create([
                        'criterion'=>$criterion->criterion,
                        'description'=>$criterion->description,
                        'max_points'=>$criterion->max_points,
                        'sort_order'=>$criterion->sort_order,
                        'criterion_version'=>1,
                        'review_status'=>'pending_review',
                        'reviewed_by'=>null,
                        'reviewed_at'=>null,
                        'review_notes'=>'Copied into a new equivalent set and requires review.',
                        'is_active'=>true,
                    ]);
                }

                return $copy;
            }

            $template = $skillTestGroup->skillTests()->orderBy('set_code')->firstOrFail();
            $copy = $template->replicate();
            $copy->set_code = $setCode;
            $copy->title = $skillTestGroup->title.' - Set '.$setCode;
            $copy->code = ($skillTestGroup->code ?: 'SG-'.$skillTestGroup->id).'-'.$setCode;
            $copy->instructions = 'Draft placeholder. Generate or enter an equivalent skills task before publishing.';
            $copy->expected_output = null;
            $copy->start_date = null;
            $copy->end_date = null;
            $copy->status = 0;
            $copy->review_status = 'pending_review';
            $copy->reviewed_by = null;
            $copy->reviewed_at = null;
            $copy->scores_released_at = null;
            $copy->scores_synced_at = null;
            $copy->save();
            return $copy;
        });

        $skillTestGroup->update([
            'expected_sets'=>max((int)$skillTestGroup->expected_sets,$skillTestGroup->skillTests()->count())
        ]);

        $governance->log('skill_equivalent_set_created', [
            'skill_test_id'=>$set->id,
        ], [
            'skill_test_group_id'=>$skillTestGroup->id,
            'set_code'=>$setCode,
            'source_skill_test_id'=>$source?->id,
        ]);

        return redirect()->route('admin.skills.edit',$set)
            ->with('status','Equivalent Skills Test Set '.$setCode.' created as a draft. Review the task and rubric before publishing.');
    }
}
