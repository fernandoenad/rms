<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AssessmentAccommodation;
use App\Models\AssessmentAttemptEvent;
use App\Models\AssessmentGroupAttemptLock;
use App\Models\AssessmentPerformanceSample;
use App\Models\Exam;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamAttemptItemOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AssessmentScoreSyncService;
use App\Services\EquivalentSetScheduleResolver;
use App\Services\WrittenAttemptScoringService;

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

        if ($exam->assessment_group_id) {
            $exam->loadMissing('assessmentGroup');
            if (!$exam->assessmentGroup || !$exam->assessmentGroup->status) {
                abort(403, 'This written assessment is not active.');
            }
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

    /**
     * Lightweight authorization path used by high-volume answer autosaves.
     * It preserves the same applicant, eligibility, exam, assignment, and
     * assessment-group rules without loading several Eloquent relationships
     * for every answer click.
     */
    protected function authorizeAnswerSave(Request $request, ExamAttempt $attempt): void
    {
        $guestEmail = $request->session()->get('guest_email');

        if (!$guestEmail) {
            abort(401);
        }

        $authorized = ExamAttempt::query()
            ->join('applications', 'applications.id', '=', 'exam_attempts.application_id')
            ->join('assessments', 'assessments.application_id', '=', 'applications.id')
            ->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
            ->leftJoin('assessment_groups', 'assessment_groups.id', '=', 'exams.assessment_group_id')
            ->where('exam_attempts.id', $attempt->id)
            ->where('applications.email', $guestEmail)
            ->whereColumn('exams.vacancy_id', 'applications.vacancy_id')
            ->where('exams.status', 1)
            ->where(function ($query) {
                $query->whereNull('exams.assessment_group_id')
                    ->orWhere('assessment_groups.status', 1);
            })
            ->where(function ($query) use ($attempt) {
                $query->where('exams.access_mode', 'all_taken_in')
                    ->orWhereExists(function ($assignment) use ($attempt) {
                        $assignment->selectRaw('1')
                            ->from('exam_assignments')
                            ->whereColumn('exam_assignments.exam_id', 'exams.id')
                            ->where('exam_assignments.application_id', $attempt->application_id);
                    });
            })
            ->exists();

        if (!$authorized) {
            abort(403, 'Assessment access is no longer available for this application.');
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

    protected function effectiveExpiry(Exam $exam, Carbon $startedAt, int $extraMinutes = 0): Carbon
    {
        $expiry = $startedAt->copy()->addMinutes((int) $exam->duration);

        if ($exam->end_date) {
            $windowEnd = Carbon::parse($exam->end_date);
            if ($windowEnd->lt($expiry)) {
                $expiry = $windowEnd;
            }
        }

        if ($extraMinutes > 0) {
            $expiry = $expiry->copy()->addMinutes($extraMinutes);
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

        $finalized = DB::transaction(function () use ($request, $attempt, $reason, $autoSubmitted) {
            $locked = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->status === 2) {
                return $locked;
            }

            $score = app(WrittenAttemptScoringService::class)->calculate($locked);

            $locked->update([
                'ended_at' => now(),
                'status' => 2,
                'auto_submitted' => $autoSubmitted,
                'auto_submit_reason' => $reason,
                'correct_answers' => $score['correct_answers'],
                'total_items' => $score['total_items'],
                'percentage' => $score['percentage'],
                'scored_at' => now(),
            ]);

            $this->event($request, $locked, $reason, [
                'correct_answers' => $score['correct_answers'],
                'total_items' => $score['total_items'],
                'answered_items' => $score['answered_items'],
            ]);

            return $locked;
        });

        app(AssessmentScoreSyncService::class)->syncWrittenAttempt($finalized);

        return $finalized;
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

    public function start(Request $request, Application $application, Exam $exam, EquivalentSetScheduleResolver $setResolver)
    {
        $this->authorizeApplication($request, $application);
        $this->authorizeExam($application, $exam);

        $exam->loadMissing('assessmentGroup');
        if ($exam->archived_at || $exam->is_paused || $exam->assessmentGroup?->archived_at || $exam->assessmentGroup?->is_paused) {
            return back()->with(
                'status_assessment',
                'New starts are temporarily unavailable for this written assessment.'
            );
        }

        $this->assertStartWindow($exam);

        $accommodation = AssessmentAccommodation::where('application_id',$application->id)
            ->where(function ($q) use ($exam) {
                if ($exam->assessment_group_id) {
                    $q->where('assessment_group_id',$exam->assessment_group_id);
                } else {
                    $q->where('exam_id',$exam->id);
                }
            })
            ->latest('id')
            ->first();
        $extraMinutes = (int) optional($accommodation)->extra_minutes;

        if ($exam->assessment_group_id) {
            $existingLock = AssessmentGroupAttemptLock::where('assessment_group_id', $exam->assessment_group_id)
                ->where('application_id', $application->id)
                ->first();

            if ($existingLock && (int) $existingLock->exam_id !== (int) $exam->id) {
                $lockedExam = Exam::find($existingLock->exam_id);

                return back()->with(
                    'status_assessment',
                    'You already started another set in this written assessment'
                    . ($lockedExam?->set_code ? ' (Set '.$lockedExam->set_code.')' : '.')
                    . ' You cannot start a second equivalent set.'
                );
            }

            if (!$existingLock) {
                $currentSet = $setResolver->currentWrittenSet((int)$exam->assessment_group_id, $application);

                if (!$currentSet || (int)$currentSet->id !== (int)$exam->id) {
                    return back()->with(
                        'status_assessment',
                        $currentSet
                            ? 'The current Written Assessment schedule is using Set '.$currentSet->set_code.'. Refresh the page and start the available set.'
                            : 'No equivalent Written Assessment set is currently open.'
                    );
                }

                try {
                    AssessmentGroupAttemptLock::create([
                        'assessment_group_id' => $exam->assessment_group_id,
                        'application_id' => $application->id,
                        'exam_id' => $exam->id,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    $existingLock = AssessmentGroupAttemptLock::where('assessment_group_id', $exam->assessment_group_id)
                        ->where('application_id', $application->id)
                        ->first();

                    if ($existingLock && (int) $existingLock->exam_id !== (int) $exam->id) {
                        return back()->with(
                            'status_assessment',
                            'Another equivalent set has already been locked to this application.'
                        );
                    }
                }
            }
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

        if ($exam->assessment_group_id) {
            AssessmentGroupAttemptLock::where('assessment_group_id', $exam->assessment_group_id)
                ->where('application_id', $application->id)
                ->where('exam_id', $exam->id)
                ->update(['exam_attempt_id' => $attempt->id]);
        }

        if ((int) $attempt->status === 2) {
            return back()->with('status_assessment', 'Attempt already completed.');
        }

        if ((int) $attempt->status === 0) {
            DB::transaction(function () use ($request, $attempt, $exam, $extraMinutes) {
                $locked = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

                if ((int) $locked->status !== 0) {
                    return;
                }

                $startedAt = now();
                $locked->update([
                    'started_at' => $startedAt,
                    'expires_at' => $this->effectiveExpiry($exam, Carbon::parse($startedAt), $extraMinutes),
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

        $accommodation = AssessmentAccommodation::where('application_id',$application->id)
            ->where(function ($q) use ($exam) {
                if ($exam->assessment_group_id) {
                    $q->where('assessment_group_id',$exam->assessment_group_id);
                } else {
                    $q->where('exam_id',$exam->id);
                }
            })
            ->latest('id')
            ->first();
        $largeText = (bool) optional($accommodation)->large_text;

        $this->event($request, $attempt, 'page_loaded');

        return view('guest.assessments.take', compact('attempt', 'exam', 'items', 'remainingSeconds', 'largeText'));
    }

    public function timeStatus(Request $request, ExamAttempt $attempt)
    {
        $application = $attempt->application;
        $this->authorizeApplication($request, $application);
        $this->authorizeExam($application, $attempt->exam);

        $fresh = $attempt->fresh();

        if ((int) $fresh->status === 2) {
            return response()->json([
                'expired' => true,
                'submitted' => true,
                'remaining_seconds' => 0,
                'server_now' => now()->toIso8601String(),
                'expires_at' => optional($fresh->expires_at)->toIso8601String(),
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        if (!$this->ensureNotExpired($request, $fresh)) {
            return response()->json([
                'expired' => true,
                'submitted' => true,
                'remaining_seconds' => 0,
                'server_now' => now()->toIso8601String(),
                'expires_at' => optional($fresh->expires_at)->toIso8601String(),
            ], 409)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $fresh = $fresh->fresh();
        $expiresAt = $fresh->expires_at ? Carbon::parse($fresh->expires_at) : null;
        $remainingSeconds = $expiresAt
            ? max(0, $expiresAt->getTimestamp() - now()->getTimestamp())
            : 0;

        return response()->json([
            'expired' => false,
            'submitted' => false,
            'remaining_seconds' => $remainingSeconds,
            'server_now' => now()->toIso8601String(),
            'expires_at' => optional($expiresAt)->toIso8601String(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function reviewStatus(Request $request, ExamAttempt $attempt)
    {
        $application = $attempt->application;
        $this->authorizeApplication($request, $application);
        $this->authorizeExam($application, $attempt->exam);

        $fresh = $attempt->fresh();

        if (!$this->ensureNotExpired($request, $fresh)) {
            return response()->json([
                'expired' => true,
                'message' => 'Assessment time has ended.',
            ], 409);
        }

        $itemIds = DB::table('written_exams')
            ->where('exam_id', $attempt->exam_id)
            ->where('status', 1)
            ->pluck('id');

        $answeredIds = DB::table('exam_attempt_answers')
            ->where('exam_attempt_id', $attempt->id)
            ->whereIn('written_exam_id', $itemIds)
            ->whereNotNull('selected_option_id')
            ->pluck('written_exam_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return response()->json([
            'expired' => false,
            'total' => $itemIds->count(),
            'answered' => $answeredIds->count(),
            'unanswered' => max(0, $itemIds->count() - $answeredIds->count()),
            'answered_item_ids' => $answeredIds,
        ]);
    }

    public function saveAnswer(Request $request, ExamAttempt $attempt)
    {
        $startedNs = hrtime(true);
        $this->authorizeAnswerSave($request, $attempt);

        // Avoid Laravel exists: rules here because the same relationship must be
        // checked against the attempt's exam anyway. One joined validation query
        // below replaces multiple existence/model lookups.
        $data = $request->validate([
            'written_exam_id' => 'required|integer|min:1',
            'selected_option_id' => 'required|integer|min:1',
        ]);

        $result = DB::transaction(function () use ($attempt, $data) {
            // A per-attempt row lock prevents an answer save from racing a final
            // submission. Different applicants lock different rows, so this does
            // not create a global assessment bottleneck.
            $locked = ExamAttempt::whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->status !== 1) {
                return ['status' => 'closed'];
            }

            if ($locked->expires_at && now()->gte(Carbon::parse($locked->expires_at))) {
                return ['status' => 'expired'];
            }

            $optionId = DB::table('written_exam_options')
                ->join('written_exams', 'written_exams.id', '=', 'written_exam_options.written_exam_id')
                ->where('written_exam_options.id', (int) $data['selected_option_id'])
                ->where('written_exams.id', (int) $data['written_exam_id'])
                ->where('written_exams.exam_id', $locked->exam_id)
                ->where('written_exams.status', 1)
                ->value('written_exam_options.id');

            if (!$optionId) {
                return ['status' => 'invalid'];
            }

            $timestamp = now();

            ExamAttemptAnswer::upsert([[
                'exam_attempt_id' => $locked->id,
                'written_exam_id' => (int) $data['written_exam_id'],
                'selected_option_id' => (int) $optionId,
                'selected_option' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]], ['exam_attempt_id', 'written_exam_id'], ['selected_option_id', 'selected_option', 'updated_at']);

            return ['status' => 'saved', 'saved_at' => $timestamp->toIso8601String()];
        });

        if ($result['status'] === 'expired') {
            $this->finalizeExpiredAttempt($request, $attempt);

            return response()->json([
                'message' => 'Assessment time has expired.',
                'expired' => true,
            ], 409);
        }

        if ($result['status'] === 'closed') {
            return response()->json([
                'message' => 'This assessment attempt is already closed.',
            ], 409);
        }

        if ($result['status'] === 'invalid') {
            return response()->json([
                'message' => 'Invalid assessment option.',
            ], 422);
        }

        // No per-answer audit-event insert: the answer row and its updated_at
        // timestamp are already the authoritative persistence record.
        if (random_int(1, 50) === 1) {
            AssessmentPerformanceSample::create([
                'operation'=>'written_answer_save',
                'exam_attempt_id'=>$attempt->id,
                'latency_ms'=>(int) round((hrtime(true)-$startedNs)/1_000_000),
                'recorded_at'=>now(),
            ]);
        }

        return response()->json([
            'message' => 'Saved',
            'saved_at' => $result['saved_at'],
        ]);
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
