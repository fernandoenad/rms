<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Jobs\ScoreSkillTestSubmission;
use App\Models\Application;
use App\Models\AssessmentAccommodation;
use App\Models\AssessmentPerformanceSample;
use App\Models\SkillTest;
use App\Models\SkillTestGroupAttemptLock;
use App\Models\SkillTestAssignment;
use App\Models\SkillTestAttempt;
use App\Models\SkillTestAttemptEvent;
use App\Models\SkillTestSubmission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SkillTestAttemptController extends Controller
{
    protected function event(Request $request, SkillTestAttempt $attempt, string $type, array $metadata = []): void
    {
        SkillTestAttemptEvent::create([
            'skill_test_attempt_id'=>$attempt->id,
            'event_type'=>$type,
            'event_at'=>now(),
            'ip_address'=>$request->ip(),
            'user_agent'=>$request->userAgent(),
            'metadata'=>$metadata ?: null,
        ]);
    }

    protected function authorizeAccess(Request $request, Application $application, SkillTest $test): void
    {
        if ($request->session()->get('guest_email') !== $application->email) abort(401);
        if ($application->assessment === null) abort(403, 'Only taken-in applicants may take assessments.');
        if ((int)$test->vacancy_id !== (int)$application->vacancy_id || (int)$test->status !== 1) abort(403);

        if ($test->skill_test_group_id) {
            $test->loadMissing('skillTestGroup');
            if (!$test->skillTestGroup || !$test->skillTestGroup->status || $test->skillTestGroup->archived_at) {
                abort(403, 'This Skills Test group is not active.');
            }
        }

        if ($test->access_mode === 'selected_applicants') {
            $assigned = SkillTestAssignment::where('skill_test_id',$test->id)
                ->where('application_id',$application->id)->exists();
            if (!$assigned) abort(403, 'You are not assigned to this skills test.');
        }
    }

    protected function expiry(SkillTest $test, Carbon $started, int $extraMinutes = 0): Carbon
    {
        $expiry = $started->copy()->addMinutes((int)$test->duration);
        if ($test->end_date && Carbon::parse($test->end_date)->lt($expiry)) {
            $expiry = Carbon::parse($test->end_date);
        }
        if ($extraMinutes > 0) {
            $expiry = $expiry->copy()->addMinutes($extraMinutes);
        }
        return $expiry;
    }

    public function start(Request $request, Application $application, SkillTest $skillTest)
    {
        $this->authorizeAccess($request,$application,$skillTest);

        $skillTest->loadMissing('skillTestGroup');
        if ($skillTest->archived_at || $skillTest->is_paused
            || $skillTest->skillTestGroup?->archived_at
            || $skillTest->skillTestGroup?->is_paused) {
            return back()->with('status_assessment','New starts are temporarily unavailable for this skills test.');
        }

        if (now()->lt($skillTest->start_date) || now()->gte($skillTest->end_date)) {
            return back()->with('status_assessment','This skills test is not currently open.');
        }

        $accommodation = AssessmentAccommodation::where('application_id',$application->id)
            ->where('skill_test_id',$skillTest->id)
            ->latest('id')
            ->first();
        $extraMinutes = (int) optional($accommodation)->extra_minutes;

        if ($skillTest->skill_test_group_id) {
            $existingLock = SkillTestGroupAttemptLock::where('skill_test_group_id',$skillTest->skill_test_group_id)
                ->where('application_id',$application->id)
                ->first();

            if ($existingLock && (int)$existingLock->skill_test_id !== (int)$skillTest->id) {
                $lockedSet = SkillTest::find($existingLock->skill_test_id);

                return back()->with(
                    'status_assessment',
                    'You already started another equivalent Skills Test set'
                    .($lockedSet?->set_code ? ' (Set '.$lockedSet->set_code.')' : '.')
                    .' You cannot start a second equivalent set.'
                );
            }

            if (!$existingLock) {
                $candidates = SkillTest::where('skill_test_group_id',$skillTest->skill_test_group_id)
                    ->where('vacancy_id',$application->vacancy_id)
                    ->where('status',1)
                    ->where(function($q){
                        $q->whereNull('start_date')->orWhere('start_date','<=',now());
                    })
                    ->where(function($q){
                        $q->whereNull('end_date')->orWhere('end_date','>',now());
                    })
                    ->where(function($q) use ($application) {
                        $q->where('access_mode','all_taken_in')
                          ->orWhereExists(function($sub) use ($application) {
                              $sub->selectRaw('1')->from('skill_test_assignments')
                                  ->whereColumn('skill_test_assignments.skill_test_id','skill_tests.id')
                                  ->where('skill_test_assignments.application_id',$application->id);
                          });
                    })
                    ->orderBy('set_code')
                    ->get(['id']);

                if ($candidates->isNotEmpty()) {
                    $index = abs(crc32($skillTest->skill_test_group_id.':'.$application->id)) % $candidates->count();
                    if ((int)$candidates[$index]->id !== (int)$skillTest->id) {
                        abort(403,'This application is assigned to another equivalent Skills Test set.');
                    }
                }

                try {
                    SkillTestGroupAttemptLock::create([
                        'skill_test_group_id'=>$skillTest->skill_test_group_id,
                        'application_id'=>$application->id,
                        'skill_test_id'=>$skillTest->id,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    $existingLock = SkillTestGroupAttemptLock::where('skill_test_group_id',$skillTest->skill_test_group_id)
                        ->where('application_id',$application->id)
                        ->first();

                    if ($existingLock && (int)$existingLock->skill_test_id !== (int)$skillTest->id) {
                        return back()->with('status_assessment','Another equivalent Skills Test set has already been locked to this application.');
                    }
                }
            }
        }

        try {
            $attempt = SkillTestAttempt::firstOrCreate(
                ['skill_test_id'=>$skillTest->id,'application_id'=>$application->id],
                ['status'=>0]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            $attempt = SkillTestAttempt::where('skill_test_id',$skillTest->id)
                ->where('application_id',$application->id)
                ->firstOrFail();
        }

        if ($skillTest->skill_test_group_id) {
            SkillTestGroupAttemptLock::where('skill_test_group_id',$skillTest->skill_test_group_id)
                ->where('application_id',$application->id)
                ->where('skill_test_id',$skillTest->id)
                ->update(['skill_test_attempt_id'=>$attempt->id]);
        }

        if ((int)$attempt->status === 3) {
            return back()->with('status_assessment','This skills-test attempt was voided. Use the specifically authorized retake task.');
        }

        if ((int)$attempt->status === 0) {
            $started = now();
            $attempt->update([
                'started_at'=>$started,
                'expires_at'=>$this->expiry($skillTest, Carbon::parse($started), $extraMinutes),
                'status'=>1,
            ]);

            $this->event($request,$attempt,'attempt_started');
        }

        return redirect()->route('guest.skills.attempts.take',$attempt);
    }

    protected function ownedAttempt(Request $request, SkillTestAttempt $attempt): SkillTestAttempt
    {
        $attempt->load('application','skillTest.rubricCriteria','submissions');
        $this->authorizeAccess($request,$attempt->application,$attempt->skillTest);
        return $attempt;
    }

    protected function finalizeIfExpired(SkillTestAttempt $attempt): bool
    {
        if (in_array((int)$attempt->status, [2,3], true)) return true;
        if ($attempt->expires_at && now()->gte($attempt->expires_at)) {
            $this->finalize($attempt);
            return true;
        }
        return false;
    }

    protected function finalize(SkillTestAttempt $attempt): void
    {
        $queueAi = false;

        DB::transaction(function () use ($attempt, &$queueAi) {
            $locked = SkillTestAttempt::with('skillTest')
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int)$locked->status === 2) return;
            if ((int)$locked->status === 3) {
                throw new \RuntimeException('Voided attempts cannot be finalized.');
            }

            $submission = SkillTestSubmission::where('skill_test_attempt_id',$locked->id)
                ->orderByDesc('version')
                ->first();

            if (!$submission) {
                $submission = SkillTestSubmission::create([
                    'skill_test_attempt_id'=>$locked->id,
                    'version'=>1,
                ]);
            }

            SkillTestSubmission::where('skill_test_attempt_id',$locked->id)
                ->update(['is_final'=>false]);

            $submission->update(['is_final'=>true,'submitted_at'=>now()]);
            $locked->update(['status'=>2,'submitted_at'=>now()]);
            $queueAi = (bool) $locked->skillTest->ai_scoring
                && (filled($submission->inline_response) || filled($submission->file_path));
        });

        // Never call the AI provider inside the database transaction.
        if ($queueAi) {
            ScoreSkillTestSubmission::dispatch($attempt->id);
        }
    }

    public function take(Request $request, SkillTestAttempt $attempt)
    {
        $attempt = $this->ownedAttempt($request,$attempt);

        if ((int)$attempt->status === 3) {
            return redirect()->route('guest.applications.show',$attempt->application)
                ->with('status_assessment','This skills-test attempt was voided. Use the authorized retake task.');
        }

        if ($this->finalizeIfExpired($attempt)) {
            return redirect()->route('guest.applications.show',$attempt->application)
                ->with('status_assessment','Skills test submitted.');
        }

        $submission = $attempt->submissions->sortByDesc('version')->first();
        $remainingSeconds = max(0, now()->diffInSeconds($attempt->expires_at, false));
        $accommodation = AssessmentAccommodation::where('application_id',$attempt->application_id)
            ->where('skill_test_id',$attempt->skill_test_id)
            ->latest('id')
            ->first();
        $largeText = (bool) optional($accommodation)->large_text;
        $this->event($request,$attempt,'page_loaded');

        return view('guest.skills.take', compact('attempt','submission','remainingSeconds','largeText'));
    }

    public function saveInline(Request $request, SkillTestAttempt $attempt)
    {
        $startedNs = hrtime(true);
        $attempt = $this->ownedAttempt($request,$attempt);
        if ($this->finalizeIfExpired($attempt)) return response()->json(['expired'=>true],409);

        $data = $request->validate(['inline_response'=>'nullable|string|max:100000']);

        $result = DB::transaction(function () use ($attempt, $data) {
            $locked = SkillTestAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ((int)$locked->status !== 1) {
                return ['saved'=>false];
            }

            $submission = SkillTestSubmission::where('skill_test_attempt_id',$locked->id)
                ->where('is_final',false)
                ->orderByDesc('version')
                ->first();

            if (!$submission) {
                $submission = SkillTestSubmission::create([
                    'skill_test_attempt_id'=>$locked->id,
                    'version'=>1,
                ]);
            }

            $submission->update(['inline_response'=>$data['inline_response'] ?? null]);

            return ['saved'=>true, 'saved_at'=>now()->toIso8601String()];
        });

        if (!$result['saved']) {
            return response()->json([
                'message'=>'This attempt is no longer editable.',
                'expired'=>true,
            ],409);
        }

        if (random_int(1, 25) === 1) {
            AssessmentPerformanceSample::create([
                'operation'=>'skill_inline_save',
                'skill_test_attempt_id'=>$attempt->id,
                'latency_ms'=>(int) round((hrtime(true)-$startedNs)/1_000_000),
                'recorded_at'=>now(),
            ]);
        }

        return response()->json(['message'=>'Saved','saved_at'=>$result['saved_at']]);
    }

    public function upload(Request $request, SkillTestAttempt $attempt)
    {
        $attempt = $this->ownedAttempt($request,$attempt);
        if ($this->finalizeIfExpired($attempt)) return response()->json(['expired'=>true],409);

        $test = $attempt->skillTest;
        $extensions = $test->allowed_extensions ?: ['docx'];
        $data = $request->validate([
            'file'=>'required|file|max:'.$test->max_file_size_kb.'|mimes:'.implode(',',$extensions),
        ]);

        $file = $data['file'];
        $path = $file->store('skill-tests/'.$test->id.'/'.$attempt->id,'local');

        try {
            $saved = DB::transaction(function () use ($attempt, $file, $path) {
                $locked = SkillTestAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

                if ((int)$locked->status !== 1) {
                    return false;
                }

                $latest = SkillTestSubmission::where('skill_test_attempt_id',$locked->id)
                    ->where('is_final',false)
                    ->orderByDesc('version')
                    ->first();

                $nextVersion = ((int) optional($latest)->version) + 1;

                SkillTestSubmission::create([
                    'skill_test_attempt_id'=>$locked->id,
                    'version'=>$nextVersion,
                    'inline_response'=>optional($latest)->inline_response,
                    'file_path'=>$path,
                    'original_filename'=>$file->getClientOriginalName(),
                    'mime_type'=>$file->getMimeType(),
                    'file_size'=>$file->getSize(),
                    'is_final'=>false,
                ]);

                return true;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        if (!$saved) {
            Storage::disk('local')->delete($path);
            return back()->with('status_assessment','This skills-test attempt is no longer editable.');
        }

        $this->event($request,$attempt,'file_uploaded',[
            'filename'=>$file->getClientOriginalName(),
            'size'=>$file->getSize(),
        ]);

        return back()->with('status_assessment','File uploaded as a new submission version. Previous uploads are retained for audit history.');
    }

    public function eventLog(Request $request, SkillTestAttempt $attempt)
    {
        $attempt = $this->ownedAttempt($request,$attempt);

        $data = $request->validate([
            'event_type'=>'required|in:tab_hidden,tab_visible,connection_lost,connection_restored,page_refreshed',
        ]);

        $this->event($request,$attempt,$data['event_type']);

        return response()->json(['message'=>'Recorded']);
    }

    public function submit(Request $request, SkillTestAttempt $attempt)
    {
        $attempt = $this->ownedAttempt($request,$attempt);
        if ((int)$attempt->status === 2) {
            return redirect()->route('guest.applications.show',$attempt->application)
                ->with('status_assessment','Skills test already submitted.');
        }

        if ((int)$attempt->status === 3) {
            return redirect()->route('guest.applications.show',$attempt->application)
                ->with('status_assessment','This attempt was voided and cannot be submitted.');
        }

        $submission = $attempt->submissions->sortByDesc('version')->first();
        $modes = $attempt->skillTest->submission_modes ?: ['inline'];
        if (!$submission || (
            in_array('inline',$modes,true) && in_array('file',$modes,true)
                ? (!$submission->inline_response && !$submission->file_path)
                : (in_array('inline',$modes,true) && !$submission->inline_response) ||
                  (in_array('file',$modes,true) && !$submission->file_path)
        )) {
            return back()->with('status_assessment','Provide the required response before submitting.');
        }

        $this->finalize($attempt);
        $this->event($request,$attempt,'manual_submit');

        return redirect()->route('guest.applications.show',$attempt->application)
            ->with('status_assessment','Skills test submitted successfully.');
    }
}
