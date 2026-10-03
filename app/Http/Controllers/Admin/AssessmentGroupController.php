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
