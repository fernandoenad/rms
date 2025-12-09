<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vacancy;
use App\Models\WrittenExam;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WrittenExamController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $exams = Exam::with('vacancy:id,position_title')
            ->withCount('writtenExams')
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'vacancy_id' => 'required|exists:vacancies,id',
            'title' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'duration' => 'required|integer|min:1',
            'shuffle_items' => 'required|boolean',
            'status' => 'required|integer|in:0,1',
        ]);

        $enrollmentKey = strtoupper(Str::random(8));

        $exam = Exam::create([
            'vacancy_id' => $data['vacancy_id'],
            'title' => $data['title'],
            'enrollment_key' => $enrollmentKey,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'duration' => $data['duration'],
            'shuffle_items' => $data['shuffle_items'],
            'status' => $data['status'],
        ]);

        return redirect()->route('admin.assessments.index')
            ->with('status', 'Written exam was successfully saved.');
    }

    public function update(Request $request, Exam $exam)
    {
        $data = $request->validate([
            'vacancy_id' => 'required|exists:vacancies,id',
            'title' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'duration' => 'required|integer|min:1',
            'shuffle_items' => 'required|boolean',
            'status' => 'required|integer|in:0,1',
        ]);

        $exam->update($data);

        return redirect()->route('admin.assessments.index')
            ->with('status', 'Written exam was successfully updated.');
    }

    public function destroy(Exam $exam)
    {
        $exam->loadCount('writtenExams');
        if ($exam->written_exams_count > 0) {
            return redirect()->route('admin.assessments.index')
                ->with('status', 'Cannot delete exam with existing items.');
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
        $exam->load(['writtenExams' => function($q){ $q->where('status',1); }]);
        $attempts = $exam->attempts()
            ->with(['application', 'answers', 'answers.item'])
            ->where('status', 2)
            ->get();

        $results = $attempts->map(function($attempt) use ($exam) {
            $total = $exam->writtenExams->count();
            $correct = 0;
            foreach ($exam->writtenExams as $item) {
                $ans = $attempt->answers->firstWhere('written_exam_id', $item->id);
                if ($ans && strtoupper($ans->selected_option) == strtoupper($item->answer_key)) {
                    $correct++;
                }
            }
            $pct = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
            return [
                'attempt' => $attempt,
                'application' => $attempt->application,
                'correct' => $correct,
                'total' => $total,
                'pct' => $pct,
            ];
        });

        return view('admin.assessments.results', [
            'exam' => $exam,
            'results' => $results,
        ]);
    }

    public function destroyAttempt(Exam $exam, ExamAttempt $attempt)
    {
        if ($attempt->exam_id !== $exam->id) {
            abort(404);
        }
        $user = auth()->user();
        Log::info('Exam attempt deleted', [
            'exam_id' => $exam->id,
            'attempt_id' => $attempt->id,
            'deleted_by_id' => $user?->id,
            'deleted_by_email' => $user?->email,
        ]);

        $attempt->delete();

        return redirect()->route('admin.assessments.results', $exam)
            ->with('status', 'Attempt deleted.');
    }

    public function regenerateKey(Exam $exam)
    {
        $exam->enrollment_key = strtoupper(Str::random(8));
        $exam->save();

        return redirect()->route('admin.assessments.index')
            ->with('status', 'Enrollment key regenerated.');
    }
}
