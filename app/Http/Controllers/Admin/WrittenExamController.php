<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAssignment;
use App\Models\Application;
use App\Models\Vacancy;
use App\Models\WrittenExam;
use App\Models\WrittenExamOption;
use App\Services\AssessmentAiService;
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

    public function index()
    {
        $exams = Exam::with('vacancy:id,position_title')
            ->withCount(['writtenExams', 'attempts'])
            ->orderByDesc('id')
            ->get();

        return view('admin.assessments.index', compact('exams'));
    }

    public function create()
    {
        $vacancies = Vacancy::orderByDesc('cycle')
            ->orderBy('position_title')
            ->get(['id', 'position_title', 'cycle']);

        return view('admin.assessments.create', compact('vacancies'));
    }

    public function edit(Exam $exam)
    {
        $vacancies = Vacancy::orderByDesc('cycle')
            ->orderBy('position_title')
            ->get(['id', 'position_title', 'cycle']);

        return view('admin.assessments.edit', compact('exam', 'vacancies'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'vacancy_id' => 'required|exists:vacancies,id',
            'title' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'duration' => 'required|integer|min:1',
            'access_mode' => 'required|in:all_taken_in,selected_applicants',
            'shuffle_items' => 'required|boolean',
            'shuffle_options' => 'required|boolean',
            'status' => 'required|integer|in:0,1',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['enrollment_key'] = strtoupper(Str::random(8));
        $data['code'] = $data['code'] ?: 'WE-' . now()->format('Ymd-His');

        Exam::create($data);

        return redirect()->route('admin.assessments.index')
            ->with('status', 'Written exam was successfully saved.');
    }

    public function update(Request $request, Exam $exam)
    {
        if ($exam->attempts()->exists()) {
            return back()->with('status', 'Exam settings are locked after an attempt has started. Create/duplicate a new set instead.');
        }

        $exam->update($this->validated($request));

        return redirect()->route('admin.assessments.index')
            ->with('status', 'Written exam was successfully updated.');
    }

    public function duplicate(Exam $exam)
    {
        $exam->load('writtenExams.options');

        $copy = DB::transaction(function () use ($exam) {
            $copy = $exam->replicate();
            $copy->title = $exam->title . ' - Copy';
            $copy->code = ($exam->code ?: 'WE-' . $exam->id) . '-COPY-' . now()->format('His');
            $copy->enrollment_key = strtoupper(Str::random(8));
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

        return redirect()->route('admin.assessments.edit', $copy)
            ->with('status', 'Exam duplicated as a draft. Set its schedule before publishing.');
    }


    public function generateAi(Request $request, Exam $exam, AssessmentAiService $ai)
    {
        if ($exam->attempts()->exists()) {
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

            DB::transaction(function () use ($exam, $items) {
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
                }
            });

            return back()->with('status', 'AI-generated items were added as reviewable exam items.');
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


    public function assignApplicants(Request $request, Exam $exam)
    {
        $data = $request->validate(['application_codes' => 'required|string']);
        $codes = collect(preg_split('/[\s,;]+/', $data['application_codes']))
            ->map(fn($x) => trim($x))->filter()->unique()->values();

        $applications = Application::where('vacancy_id', $exam->vacancy_id)
            ->whereIn('application_code', $codes)
            ->whereHas('assessment')
            ->get(['id','application_code']);

        foreach ($applications as $application) {
            ExamAssignment::firstOrCreate([
                'exam_id' => $exam->id,
                'application_id' => $application->id,
            ]);
        }

        return back()->with('status', $applications->count().' taken-in applicant(s) assigned.');
    }

    public function destroy(Exam $exam)
    {
        if ($exam->attempts()->exists()) {
            return back()->with('status', 'Cannot delete an exam with attempts. Archive it instead.');
        }

        $exam->delete();

        return redirect()->route('admin.assessments.index')
            ->with('status', 'Written exam was successfully deleted.');
    }

    public function toggleStatus(Exam $exam)
    {
        $exam->status = $exam->status == 1 ? 0 : 1;
        $exam->save();

        return redirect()->route('admin.assessments.index')
            ->with('status', 'Written exam status updated.');
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

        Log::warning('Exam attempt hard-deleted', [
            'exam_id' => $exam->id,
            'attempt_id' => $attempt->id,
            'deleted_by_id' => auth()->id(),
            'deleted_by_email' => optional(auth()->user())->email,
        ]);

        $attempt->delete();

        return redirect()->route('admin.assessments.results', $exam)
            ->with('status', 'Attempt deleted. Consider using void/retake workflow for operational use.');
    }

    public function regenerateKey(Exam $exam)
    {
        $exam->enrollment_key = strtoupper(Str::random(8));
        $exam->save();

        return redirect()->route('admin.assessments.index')
            ->with('status', 'Enrollment key regenerated.');
    }
}
