<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentGroup;
use App\Models\Vacancy;
use Illuminate\Http\Request;
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'vacancy_id' => 'required|exists:vacancies,id',
            'title' => 'required|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('assessment_groups', 'code')
                    ->where(fn ($q) => $q->where('vacancy_id', $request->vacancy_id)),
            ],
            'status' => 'required|boolean',
        ]);

        AssessmentGroup::create($data);

        return redirect()->route('admin.assessment_groups.index')
            ->with('status', 'Assessment group created. Add written exam sets to this group.');
    }

    public function results(AssessmentGroup $assessmentGroup)
    {
        $assessmentGroup->load('vacancy:id,position_title');

        // One aggregate query for dashboard metrics. This avoids loading thousands
        // of attempts into PHP just to count statuses.
        $dashboard = \App\Models\ExamAttempt::query()
            ->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
            ->where('exams.assessment_group_id', $assessmentGroup->id)
            ->whereNotNull('exam_attempts.started_at')
            ->selectRaw('COUNT(*) as attempted')
            ->selectRaw('SUM(CASE WHEN exam_attempts.status = 1 AND (exam_attempts.expires_at IS NULL OR exam_attempts.expires_at > ?) THEN 1 ELSE 0 END) as taking_now', [now()])
            ->selectRaw('SUM(CASE WHEN exam_attempts.status = 1 AND exam_attempts.expires_at IS NOT NULL AND exam_attempts.expires_at <= ? THEN 1 ELSE 0 END) as awaiting_timeout_finalization', [now()])
            ->selectRaw('SUM(CASE WHEN exam_attempts.status = 2 THEN 1 ELSE 0 END) as submitted')
            ->first();

        $dashboard->completion_rate = (int) $dashboard->attempted > 0
            ? round(((int) $dashboard->submitted / (int) $dashboard->attempted) * 100, 1)
            : 0;

        // Keep the operational list paginated. In-progress attempts are shown first,
        // followed by completed attempts, so admins can see who is currently taking.
        $attempts = \App\Models\ExamAttempt::whereHas('exam', function ($query) use ($assessmentGroup) {
                $query->where('assessment_group_id', $assessmentGroup->id);
            })
            ->with([
                'application:id,application_code,first_name,middle_name,last_name',
                'exam:id,assessment_group_id,title,set_code',
            ])
            ->whereNotNull('started_at')
            ->whereIn('status', [1, 2])
            ->orderByRaw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
            ->orderByDesc('started_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.assessment_groups.results', compact('assessmentGroup', 'attempts', 'dashboard'));
    }

    public function edit(AssessmentGroup $assessmentGroup)
    {
        $vacancies = Vacancy::orderByDesc('cycle')
            ->orderBy('position_title')
            ->get(['id', 'position_title', 'cycle']);

        $assessmentGroup->load(['exams' => fn ($q) => $q->orderBy('start_date')]);

        return view('admin.assessment_groups.edit', compact('assessmentGroup', 'vacancies'));
    }

    public function update(Request $request, AssessmentGroup $assessmentGroup)
    {
        $data = $request->validate([
            'vacancy_id' => 'required|exists:vacancies,id',
            'title' => 'required|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('assessment_groups', 'code')
                    ->ignore($assessmentGroup->id)
                    ->where(fn ($q) => $q->where('vacancy_id', $request->vacancy_id)),
            ],
            'status' => 'required|boolean',
        ]);

        if ($assessmentGroup->exams()->exists()
            && (int) $data['vacancy_id'] !== (int) $assessmentGroup->vacancy_id) {
            return back()->withInput()->withErrors([
                'vacancy_id' => 'The position cannot be changed after exam sets have been added.',
            ]);
        }

        $assessmentGroup->update($data);

        return redirect()->route('admin.assessment_groups.index')
            ->with('status', 'Assessment group updated.');
    }
}
