<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AssessmentAttemptEvent;
use App\Models\Exam;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamAttemptItemOrder;
use App\Models\WrittenExam;
use App\Models\WrittenExamOption;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamAttemptController extends Controller
{
    protected function authorizeApplication(Request $request, Application $application): void
    {
        if ($request->session()->get('guest_email') !== $application->email) {
            abort(401);
        }

        // Existing RMS take-in indicator: a taken-in application has an Assessment record.
        if ($application->assessment === null) {
            abort(403, 'Only taken-in applicants may take assessments.');
        }
    }

    protected function authorizeExam(Application $application, Exam $exam): void
    {
        if ((int) $exam->vacancy_id !== (int) $application->vacancy_id || (int) $exam->status !== 1) {
            abort(403, 'Exam not available for this application.');
        }

        if ($exam->access_mode === 'selected_applicants') {
            $assigned = ExamAssignment::where('exam_id', $exam->id)
                ->where('application_id', $application->id)
                ->exists();

            if (!$assigned) {
                abort(403, 'You are not assigned to this assessment.');
            }
        }
    }

    protected function assertStartWindow(Exam $exam): void
    {
        if ($exam->start_date && now()->lt($exam->start_date)) {
            abort(403, 'This assessment is not open yet.');
        }

        if ($exam->end_date && now()->gte($exam->end_date)) {
            abort(403, 'This assessment is already closed.');
        }
    }

    protected function event(Request $request, ExamAttempt $attempt, string $type, array $metadata = []): void
    {
        AssessmentAttemptEvent::create([
            'exam_attempt_id' => $attempt->id,
            'event_type' => $type,
            'event_at' => now(),
            'ip_address' => $request->ip(), // audit only; never an identity/uniqueness key
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata ?: null,
        ]);
    }

    protected function effectiveExpiry(Exam $exam, Carbon $startedAt): Carbon
    {
        $expiry = $startedAt->copy()->addMinutes((int) $exam->duration);

        if ($exam->end_date) {
            $windowEnd = Carbon::parse($exam->end_date);
            if ($windowEnd->lt($expiry)) {
                $expiry = $windowEnd;
            }
        }

        return $expiry;
    }

    protected function finalizeAttempt(
        Request $request,
        ExamAttempt $attempt,
        string $reason,
        bool $autoSubmitted
    ): ExamAttempt {
        if ((int) $attempt->status === 2) {
            return $attempt;
        }

        return DB::transaction(function () use ($request, $attempt, $reason, $autoSubmitted) {
            $locked = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->status === 2) {
                return $locked;
            }

            $exam = $locked->exam()->with(['writtenExams.options'])->firstOrFail();
            $answers = $locked->answers()->with('selectedOption')->get();
            $items = $exam->writtenExams->where('status', 1);

            $correct = 0;
            foreach ($items as $item) {
                $answer = $answers->firstWhere('written_exam_id', $item->id);

                if ($answer && $answer->selectedOption && $answer->selectedOption->is_correct) {
                    $correct++;
                    continue;
                }

                if ($answer && !$answer->selected_option_id && $answer->selected_option
                    && strtoupper($answer->selected_option) === strtoupper((string) $item->answer_key)) {
                    $correct++;
                }
            }

            $total = $items->count();
            $percentage = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

            $locked->update([
                'ended_at' => now(),
                'status' => 2,
                'auto_submitted' => $autoSubmitted,
                'auto_submit_reason' => $reason,
                'correct_answers' => $correct,
                'total_items' => $total,
                'percentage' => $percentage,
                'scored_at' => now(),
            ]);

            $this->event($request, $locked, $reason, [
                'correct_answers' => $correct,
                'total_items' => $total,
                'answered_items' => $answers->whereNotNull('selected_option_id')->count(),
            ]);

            return $locked;
        });
    }

    protected function finalizeExpiredAttempt(Request $request, ExamAttempt $attempt): ExamAttempt
    {
        return $this->finalizeAttempt($request, $attempt, 'timeout', true);
    }

    protected function ensureNotExpired(Request $request, ExamAttempt $attempt): bool
    {
        if ((int) $attempt->status === 2) {
            return false;
        }

        $expiresAt = $attempt->expires_at ? Carbon::parse($attempt->expires_at) : null;

        if ($expiresAt && now()->gte($expiresAt)) {
            $this->finalizeExpiredAttempt($request, $attempt);
            return false;
        }

        return true;
    }

    protected function initializeOrders(ExamAttempt $attempt, Exam $exam): void
    {
        if (!$attempt->question_order) {
            $itemIds = $exam->writtenExams()->where('status', 1)->pluck('id')->all();

            if ($exam->shuffle_items) {
                shuffle($itemIds);
            }

            $attempt->update(['question_order' => json_encode($itemIds)]);
        }

        $items = $exam->writtenExams()->where('status', 1)->with('options')->get();

        foreach ($items as $item) {
            $optionIds = $item->options->pluck('id')->all();

            if ($exam->shuffle_options) {
                shuffle($optionIds);
            }

            ExamAttemptItemOrder::firstOrCreate(
                ['exam_attempt_id' => $attempt->id, 'written_exam_id' => $item->id],
                ['option_order' => $optionIds]
            );
        }
    }

    public function start(Request $request, Application $application, Exam $exam)
    {
        $this->authorizeApplication($request, $application);
        $this->authorizeExam($application, $exam);
        $this->assertStartWindow($exam);

        $request->validate(['enrollment_key' => 'required|string']);

        if (!hash_equals((string) $exam->enrollment_key, trim((string) $request->enrollment_key))) {
            return back()->with('status_assessment', 'Invalid enrollment key.');
        }

        try {
            $attempt = ExamAttempt::firstOrCreate(
                ['exam_id' => $exam->id, 'application_id' => $application->id],
                ['status' => 0]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Handles simultaneous/double Start requests against the unique constraint.
            $attempt = ExamAttempt::where('exam_id', $exam->id)
                ->where('application_id', $application->id)
                ->firstOrFail();
        }

        if ((int) $attempt->status === 2) {
            return back()->with('status_assessment', 'Attempt already completed.');
        }

        if ((int) $attempt->status === 0) {
            DB::transaction(function () use ($request, $attempt, $exam) {
                $locked = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

                if ((int) $locked->status !== 0) {
                    return;
                }

                $startedAt = now();
                $locked->update([
                    'started_at' => $startedAt,
                    'expires_at' => $this->effectiveExpiry($exam, Carbon::parse($startedAt)),
                    'status' => 1,
                ]);

                $this->initializeOrders($locked, $exam);
                $this->event($request, $locked, 'attempt_started');
            });
        }

        return redirect()->route('guest.assessments.attempts.take', $attempt);
    }

    public function take(Request $request, ExamAttempt $attempt)
    {
        $application = $attempt->application;
        $this->authorizeApplication($request, $application);
        $this->authorizeExam($application, $attempt->exam);

        if (!$this->ensureNotExpired($request, $attempt->fresh())) {
            return redirect()->route('guest.applications.show', $application)
                ->with('status_assessment', 'Assessment time has ended and your saved answers were submitted.');
        }

        $attempt = $attempt->fresh()->load([
            'exam.writtenExams.options',
            'answers.selectedOption',
            'itemOrders',
        ]);

        $exam = $attempt->exam;
        $itemsCollection = $exam->writtenExams->where('status', 1);

        $questionOrder = json_decode((string) $attempt->question_order, true) ?: [];
        $items = $itemsCollection->sortBy(function ($item) use ($questionOrder) {
            $position = array_search($item->id, $questionOrder, true);
            return $position === false ? PHP_INT_MAX : $position;
        })->values();

        $optionOrders = $attempt->itemOrders->keyBy('written_exam_id');
        foreach ($items as $item) {
            $order = optional($optionOrders->get($item->id))->option_order ?: $item->options->pluck('id')->all();
            $item->setRelation('displayOptions', $item->options->sortBy(function ($option) use ($order) {
                $position = array_search($option->id, $order, true);
                return $position === false ? PHP_INT_MAX : $position;
            })->values());
        }

        $remainingSeconds = max(0, now()->diffInSeconds(Carbon::parse($attempt->expires_at), false));

        $this->event($request, $attempt, 'page_loaded');

        return view('guest.assessments.take', compact('attempt', 'exam', 'items', 'remainingSeconds'));
    }

    public function saveAnswer(Request $request, ExamAttempt $attempt)
    {
        $application = $attempt->application;
        $this->authorizeApplication($request, $application);
        $this->authorizeExam($application, $attempt->exam);

        if (!$this->ensureNotExpired($request, $attempt->fresh())) {
            return response()->json(['message' => 'Assessment time has expired.', 'expired' => true], 409);
        }

        $data = $request->validate([
            'written_exam_id' => 'required|exists:written_exams,id',
            'selected_option_id' => 'required|exists:written_exam_options,id',
        ]);

        $item = WrittenExam::whereKey($data['written_exam_id'])->firstOrFail();
        $option = WrittenExamOption::whereKey($data['selected_option_id'])->firstOrFail();

        if ((int) $item->exam_id !== (int) $attempt->exam_id || (int) $option->written_exam_id !== (int) $item->id) {
            return response()->json(['message' => 'Invalid assessment option.'], 422);
        }

        ExamAttemptAnswer::upsert([[
            'exam_attempt_id' => $attempt->id,
            'written_exam_id' => $item->id,
            'selected_option_id' => $option->id,
            'selected_option' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['exam_attempt_id', 'written_exam_id'], ['selected_option_id', 'selected_option', 'updated_at']);

        $this->event($request, $attempt, 'answer_saved', ['written_exam_id' => $item->id]);

        return response()->json(['message' => 'Saved', 'saved_at' => now()->toIso8601String()]);
    }

    public function eventLog(Request $request, ExamAttempt $attempt)
    {
        $application = $attempt->application;
        $this->authorizeApplication($request, $application);
        $this->authorizeExam($application, $attempt->exam);

        $data = $request->validate([
            'event_type' => 'required|in:tab_hidden,tab_visible,connection_lost,connection_restored,page_refreshed',
        ]);

        $this->event($request, $attempt, $data['event_type']);

        return response()->json(['message' => 'Recorded']);
    }

    public function submit(Request $request, ExamAttempt $attempt)
    {
        $application = $attempt->application;
        $this->authorizeApplication($request, $application);
        $this->authorizeExam($application, $attempt->exam);

        $freshAttempt = $attempt->fresh();

        // Timeout submission remains automatic and needs no applicant confirmation.
        if (!$this->ensureNotExpired($request, $freshAttempt)) {
            return redirect()->route('guest.applications.show', $application)
                ->with('status_assessment', 'Assessment submitted.');
        }

        // Early submission is allowed only after the applicant explicitly confirms it.
        $request->validate([
            'confirmed' => 'required|accepted',
        ]);

        $this->finalizeAttempt($request, $freshAttempt, 'manual_submit', false);

        return redirect()->route('guest.applications.show', $application)
            ->with('status_assessment', 'Assessment submitted successfully.');
    }
}
