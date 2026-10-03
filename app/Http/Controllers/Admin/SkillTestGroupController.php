<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SkillTest;
use App\Models\SkillTestGroup;
use App\Models\Vacancy;
use App\Services\AssessmentGovernanceService;
use App\Services\AssessmentScoreSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'start_date'=>'required|date',
            'end_date'=>'required|date|after:start_date',
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
            'start_date'=>$data['start_date'],
            'end_date'=>$data['end_date'],
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
            ->with('status','Skills Test group created with '.(int)$data['expected_sets'].' draft equivalent set(s). Configure each task/rubric, review, then publish.');
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
                    'start_date'=>$data['start_date'],
                    'end_date'=>$data['end_date'],
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
