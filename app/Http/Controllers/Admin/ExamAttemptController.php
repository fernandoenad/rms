<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\WrittenExam;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamAttemptController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function authorizeAccess(Application $application, Exam $exam)
    {
        if ($exam->vacancy_id !== $application->vacancy_id || $exam->status != 1) {
            abort(403, 'Exam not available for this application.');
        }
        // Only allow if assessment relationship exists (per requirement)
        if ($application->assessment === null) {
            abort(403, 'Applicant is not eligible to take assessments.');
        }
    }

    public function start(Application $application, Exam $exam)
    {
        $this->authorizeAccess($application, $exam);

        $attempt = ExamAttempt::firstOrCreate(
            ['exam_id' => $exam->id, 'application_id' => $application->id],
            ['status' => 0]
        );

        if ($attempt->status == 2) {
            return redirect()->back()->with('status', 'Attempt already submitted.');
        }

        if ($attempt->status == 0) {
            $attempt->update([
                'started_at' => Carbon::now(),
                'status' => 1,
            ]);
            if (!$attempt->question_order) {
                $items = $exam->writtenExams()->where('status', 1)->pluck('id')->toArray();
                if ($exam->shuffle_items) {
                    shuffle($items);
                }
                $attempt->update(['question_order' => json_encode($items)]);
            }
        }

        return redirect()->route('admin.assessments.attempts.take', $attempt);
    }

    public function take(ExamAttempt $attempt)
    {
        if ($attempt->application && $attempt->application->assessment === null) {
            abort(403, 'Applicant is not eligible to take assessments.');
        }

        $attempt->load('exam.writtenExams', 'answers', 'application');

        if ($attempt->status == 2) {
            return redirect()->back()->with('status', 'Attempt already submitted.');
        }

        $exam = $attempt->exam;
        $itemsCollection = $exam->writtenExams->where('status', 1);
        $items = $itemsCollection;
        if ($attempt->question_order) {
            $order = json_decode($attempt->question_order, true) ?: [];
            $items = $itemsCollection->sortBy(function($item) use ($order) {
                $pos = array_search($item->id, $order);
                return $pos !== false ? $pos : PHP_INT_MAX;
            });
        }

        $durationMinutes = $exam->duration ?? 0;
        $elapsedSeconds = $attempt->started_at ? now()->diffInSeconds($attempt->started_at) : 0;
        $remainingSeconds = max(0, ($durationMinutes * 60) - $elapsedSeconds);

        return view('admin.assessments.attempts.take', [
            'attempt' => $attempt,
            'exam' => $exam,
            'items' => $items,
            'remainingSeconds' => $remainingSeconds,
        ]);
    }

    public function saveAnswer(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->application && $attempt->application->assessment === null) {
            return response()->json(['message' => 'Applicant not eligible'], 403);
        }

        if ($attempt->status == 2) {
            return response()->json(['message' => 'Attempt already submitted'], 400);
        }

        $data = $request->validate([
            'written_exam_id' => 'required|exists:written_exams,id',
            'selected_option' => 'nullable|in:A,B,C,D',
        ]);

        $item = WrittenExam::find($data['written_exam_id']);
        if ($item->exam_id !== $attempt->exam_id) {
            return response()->json(['message' => 'Invalid item'], 400);
        }

        ExamAttemptAnswer::updateOrCreate(
            ['exam_attempt_id' => $attempt->id, 'written_exam_id' => $item->id],
            ['selected_option' => $data['selected_option']]
        );

        return response()->json(['message' => 'Saved']);
    }

    public function submit(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->status == 2) {
            return redirect()->route('admin.assessments.index')->with('status', 'Attempt already submitted.');
        }

        $attempt->update([
            'ended_at' => Carbon::now(),
            'status' => 2,
        ]);

        return redirect()->route('admin.assessments.index')->with('status', 'Assessment submitted.');
    }
}
