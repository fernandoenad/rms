<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SkillTest;
use App\Models\SkillTestAttempt;
use App\Models\Vacancy;
use App\Services\AssessmentAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SkillTestController extends Controller
{
    public function __construct() { $this->middleware('auth'); }

    public function index()
    {
        $tests = SkillTest::with('vacancy:id,position_title')
            ->withCount('attempts')->orderByDesc('id')->get();
        return view('admin.skills.index',compact('tests'));
    }

    public function create()
    {
        $vacancies = Vacancy::orderByDesc('cycle')->orderBy('position_title')
            ->get(['id','position_title','cycle']);
        return view('admin.skills.create',compact('vacancies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vacancy_id'=>'required|exists:vacancies,id',
            'title'=>'required|string|max:255',
            'code'=>'nullable|string|max:100',
            'instructions'=>'required|string',
            'expected_output'=>'nullable|string',
            'start_date'=>'required|date',
            'end_date'=>'required|date|after:start_date',
            'duration'=>'required|integer|min:1',
            'access_mode'=>'required|in:all_taken_in,selected_applicants',
            'submission_modes'=>'required|array|min:1',
            'submission_modes.*'=>'in:inline,file',
            'allowed_extensions'=>'nullable|string',
            'max_file_size_kb'=>'required|integer|min:100|max:51200',
            'ai_scoring'=>'required|boolean',
            'status'=>'required|boolean',
        ]);

        $data['code'] = $data['code'] ?: 'ST-'.now()->format('Ymd-His');
        $data['allowed_extensions'] = collect(explode(',', $data['allowed_extensions'] ?? 'docx'))
            ->map(fn($x)=>strtolower(trim($x)))->filter()->values()->all();

        $test = SkillTest::create($data);
        return redirect()->route('admin.skills.edit',$test)->with('status','Skills test created. Add or generate the rubric next.');
    }

    public function edit(SkillTest $skillTest)
    {
        $skillTest->load('rubricCriteria','vacancy');
        return view('admin.skills.edit',compact('skillTest'));
    }

    public function generateAi(Request $request, SkillTest $skillTest, AssessmentAiService $ai)
    {
        if ($skillTest->attempts()->exists()) return back()->with('status','Cannot regenerate after attempts exist.');

        try {
            $payload = $ai->generateSkillsTask($skillTest->vacancy, (int)$skillTest->duration);
            DB::transaction(function () use ($skillTest,$payload) {
                $skillTest->update([
                    'title'=>$payload['title'],
                    'instructions'=>$payload['instructions'],
                    'expected_output'=>$payload['expected_output'] ?? null,
                ]);
                $skillTest->rubricCriteria()->delete();
                foreach ($payload['rubric'] as $i=>$criterion) {
                    $skillTest->rubricCriteria()->create([
                        'criterion'=>$criterion['criterion'],
                        'description'=>$criterion['description'] ?? null,
                        'max_points'=>$criterion['max_points'],
                        'sort_order'=>$i,
                    ]);
                }
            });
            return back()->with('status','AI-generated task and rubric saved for human review.');
        } catch (\Throwable $e) {
            return back()->with('status','AI generation failed: '.$e->getMessage());
        }
    }

    public function saveRubric(Request $request, SkillTest $skillTest)
    {
        if ($skillTest->attempts()->exists()) return back()->with('status','Rubric is locked after attempts exist.');

        $criteria = collect($request->input('criteria', []))
            ->filter(fn($row) => trim((string)($row['criterion'] ?? '')) !== '')
            ->values()
            ->all();

        $validator = validator(['criteria'=>$criteria], [
            'criteria'=>'required|array|min:1',
            'criteria.*.criterion'=>'required|string|max:255',
            'criteria.*.description'=>'nullable|string',
            'criteria.*.max_points'=>'required|numeric|min:0.01|max:100',
        ]);
        $data = $validator->validate();

        $total = collect($data['criteria'])->sum('max_points');
        if (abs($total - 100) > 0.01) return back()->with('status','Rubric must total exactly 100 points.');

        DB::transaction(function () use ($skillTest,$data) {
            $skillTest->rubricCriteria()->delete();
            foreach ($data['criteria'] as $i=>$criterion) {
                $skillTest->rubricCriteria()->create($criterion + ['sort_order'=>$i]);
            }
        });

        return back()->with('status','Rubric saved.');
    }

    public function results(SkillTest $skillTest)
    {
        $attempts = $skillTest->attempts()->with(['application','submissions','aiEvaluations'])
            ->where('status',2)->paginate(25);
        return view('admin.skills.results',compact('skillTest','attempts'));
    }

    public function finalizeScore(Request $request, SkillTest $skillTest, SkillTestAttempt $attempt)
    {
        abort_unless((int)$attempt->skill_test_id === (int)$skillTest->id,404);
        $data = $request->validate(['final_score'=>'required|numeric|min:0|max:100']);
        $attempt->update([
            'final_score'=>$data['final_score'],
            'finalized_by'=>auth()->id(),
            'evaluated_at'=>now(),
        ]);
        return back()->with('status','Human-final score saved.');
    }
}
