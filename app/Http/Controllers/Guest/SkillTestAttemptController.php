<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Jobs\ScoreSkillTestSubmission;
use App\Models\Application;
use App\Models\SkillTest;
use App\Models\SkillTestAssignment;
use App\Models\SkillTestAttempt;
use App\Models\SkillTestSubmission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SkillTestAttemptController extends Controller
{
    protected function authorizeAccess(Request $request, Application $application, SkillTest $test): void
    {
        if ($request->session()->get('guest_email') !== $application->email) abort(401);
        if ($application->assessment === null) abort(403, 'Only taken-in applicants may take assessments.');
        if ((int)$test->vacancy_id !== (int)$application->vacancy_id || (int)$test->status !== 1) abort(403);

        if ($test->access_mode === 'selected_applicants') {
            $assigned = SkillTestAssignment::where('skill_test_id',$test->id)
                ->where('application_id',$application->id)->exists();
            if (!$assigned) abort(403, 'You are not assigned to this skills test.');
        }
    }

    protected function expiry(SkillTest $test, Carbon $started): Carbon
    {
        $expiry = $started->copy()->addMinutes((int)$test->duration);
        if ($test->end_date && Carbon::parse($test->end_date)->lt($expiry)) {
            $expiry = Carbon::parse($test->end_date);
        }
        return $expiry;
    }

    public function start(Request $request, Application $application, SkillTest $skillTest)
    {
        $this->authorizeAccess($request,$application,$skillTest);

        if (now()->lt($skillTest->start_date) || now()->gte($skillTest->end_date)) {
            return back()->with('status_assessment','This skills test is not currently open.');
        }

        $attempt = SkillTestAttempt::firstOrCreate(
            ['skill_test_id'=>$skillTest->id,'application_id'=>$application->id],
            ['status'=>0]
        );

        if ((int)$attempt->status === 0) {
            $started = now();
            $attempt->update([
                'started_at'=>$started,
                'expires_at'=>$this->expiry($skillTest, Carbon::parse($started)),
                'status'=>1,
            ]);
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
        if ((int)$attempt->status === 2) return true;
        if ($attempt->expires_at && now()->gte($attempt->expires_at)) {
            $this->finalize($attempt);
            return true;
        }
        return false;
    }

    protected function finalize(SkillTestAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt) {
            $locked = SkillTestAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ((int)$locked->status === 2) return;

            $submission = SkillTestSubmission::firstOrCreate(
                ['skill_test_attempt_id'=>$locked->id,'version'=>1],
                []
            );
            $submission->update(['is_final'=>true,'submitted_at'=>now()]);
            $locked->update(['status'=>2,'submitted_at'=>now()]);

            if ($locked->skillTest->ai_scoring) {
                ScoreSkillTestSubmission::dispatch($locked->id);
            }
        });
    }

    public function take(Request $request, SkillTestAttempt $attempt)
    {
        $attempt = $this->ownedAttempt($request,$attempt);

        if ($this->finalizeIfExpired($attempt)) {
            return redirect()->route('guest.applications.show',$attempt->application)
                ->with('status_assessment','Skills test submitted.');
        }

        $submission = $attempt->submissions->sortByDesc('version')->first();
        $remainingSeconds = max(0, now()->diffInSeconds($attempt->expires_at, false));

        return view('guest.skills.take', compact('attempt','submission','remainingSeconds'));
    }

    public function saveInline(Request $request, SkillTestAttempt $attempt)
    {
        $attempt = $this->ownedAttempt($request,$attempt);
        if ($this->finalizeIfExpired($attempt)) return response()->json(['expired'=>true],409);

        $data = $request->validate(['inline_response'=>'nullable|string|max:100000']);
        $submission = SkillTestSubmission::firstOrCreate(
            ['skill_test_attempt_id'=>$attempt->id,'version'=>1],
            []
        );
        $submission->update(['inline_response'=>$data['inline_response'] ?? null]);

        return response()->json(['message'=>'Saved','saved_at'=>now()->toIso8601String()]);
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

        $submission = SkillTestSubmission::firstOrCreate(
            ['skill_test_attempt_id'=>$attempt->id,'version'=>1],
            []
        );

        if ($submission->file_path) Storage::disk('local')->delete($submission->file_path);

        $file = $data['file'];
        $path = $file->store('skill-tests/'.$test->id.'/'.$attempt->id,'local');
        $submission->update([
            'file_path'=>$path,
            'original_filename'=>$file->getClientOriginalName(),
            'mime_type'=>$file->getMimeType(),
            'file_size'=>$file->getSize(),
        ]);

        return back()->with('status_assessment','File uploaded and saved.');
    }

    public function submit(Request $request, SkillTestAttempt $attempt)
    {
        $attempt = $this->ownedAttempt($request,$attempt);
        if ((int)$attempt->status === 2) {
            return redirect()->route('guest.applications.show',$attempt->application)
                ->with('status_assessment','Skills test already submitted.');
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

        return redirect()->route('guest.applications.show',$attempt->application)
            ->with('status_assessment','Skills test submitted successfully.');
    }
}
