<?php

namespace App\Http\Controllers\Guest;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Inquiry;
use App\Models\Vacancy;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Mail;
use App\Mail\UpdateMail;
use App\Services\EquivalentSetScheduleResolver;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {


        return view('guest.applications.index');
    }

    public function lookup(Request $request)
    {
        return redirect()->route('applicant.dashboard');
    }

    public function my(Request $request)
    {
        return redirect()->route('applicant.dashboard');
    }

    public function show(Request $request, Application $application, EquivalentSetScheduleResolver $setResolver)
    {
        $applicationInquiries = $application->inquiries;
        $exams = \App\Models\Exam::where('vacancy_id', $application->vacancy_id)
            ->where(function ($q) use ($application) {
                $q->where('status', 1)
                  ->orWhereHas('attempts', fn ($attempts) => $attempts->where('application_id', $application->id));
            })
            ->where(function ($q) use ($application) {
                $q->where('access_mode', 'all_taken_in')
                  ->orWhereExists(function ($sub) use ($application) {
                      $sub->selectRaw('1')->from('exam_assignments')
                          ->whereColumn('exam_assignments.exam_id', 'exams.id')
                          ->where('exam_assignments.application_id', $application->id);
                  })
                  ->orWhereHas('attempts', fn ($attempts) => $attempts->where('application_id', $application->id));
            })
            ->with([
                'assessmentGroup:id,title,status,score_release_policy,scores_released_at',
                'attempts' => function($q) use ($application) {
                    $q->where('application_id', $application->id);
                },
            ])
            ->orderBy('start_date')
            ->get();

        $groupLocks = \App\Models\AssessmentGroupAttemptLock::where('application_id', $application->id)
            ->get()
            ->keyBy('assessment_group_id');

        $visibleExams = collect();

        foreach ($exams->whereNull('assessment_group_id') as $standaloneExam) {
            $visibleExams->push($standaloneExam);
        }

        foreach ($exams->whereNotNull('assessment_group_id')->groupBy('assessment_group_id') as $groupId => $sets) {
            $lock = $groupLocks->get($groupId);

            if ($lock) {
                $lockedSet = $sets->firstWhere('id', $lock->exam_id);

                if (!$lockedSet) {
                    $lockedSet = \App\Models\Exam::with([
                            'assessmentGroup:id,title,status,score_release_policy,scores_released_at',
                            'attempts' => fn ($q) => $q->where('application_id', $application->id),
                        ])
                        ->whereKey($lock->exam_id)
                        ->where('vacancy_id', $application->vacancy_id)
                        ->first();
                }

                if ($lockedSet) {
                    $visibleExams->push($lockedSet);
                }

                continue;
            }

            $visibleSet = $setResolver->currentWrittenSet((int)$groupId, $application)
                ?: $setResolver->nextWrittenSet((int)$groupId, $application);

            if ($visibleSet) {
                $visibleExams->push($visibleSet);
            }
        }

        $exams = $visibleExams->sortBy('start_date')->values();

        $skillTests = \App\Models\SkillTest::where('vacancy_id', $application->vacancy_id)
            ->where(function ($q) use ($application) {
                $q->where('status', 1)
                  ->orWhereHas('attempts', fn ($attempts) => $attempts->where('application_id', $application->id));
            })
            ->where(function ($q) use ($application) {
                $q->where('access_mode', 'all_taken_in')
                  ->orWhereExists(function ($sub) use ($application) {
                      $sub->selectRaw('1')->from('skill_test_assignments')
                          ->whereColumn('skill_test_assignments.skill_test_id', 'skill_tests.id')
                          ->where('skill_test_assignments.application_id', $application->id);
                  })
                  ->orWhereHas('attempts', fn ($attempts) => $attempts->where('application_id', $application->id));
            })
            ->with([
                'skillTestGroup:id,title,status,is_paused,archived_at,score_release_policy,scores_released_at',
                'attempts' => function($q) use ($application) {
                    $q->where('application_id', $application->id);
                },
            ])
            ->orderBy('start_date')
            ->get();

        $skillGroupLocks = \App\Models\SkillTestGroupAttemptLock::where('application_id',$application->id)
            ->get()
            ->keyBy('skill_test_group_id');

        $visibleSkillTests = collect();

        foreach ($skillTests->whereNull('skill_test_group_id') as $standalone) {
            $visibleSkillTests->push($standalone);
        }

        foreach ($skillTests->whereNotNull('skill_test_group_id')->groupBy('skill_test_group_id') as $groupId => $sets) {
            $lock = $skillGroupLocks->get($groupId);

            if ($lock) {
                $lockedSet = $sets->firstWhere('id',$lock->skill_test_id);

                if (!$lockedSet) {
                    $lockedSet = \App\Models\SkillTest::with([
                            'skillTestGroup:id,title,status,is_paused,archived_at,score_release_policy,scores_released_at',
                            'attempts'=>fn($q)=>$q->where('application_id',$application->id),
                        ])
                        ->whereKey($lock->skill_test_id)
                        ->where('vacancy_id',$application->vacancy_id)
                        ->first();
                }

                if ($lockedSet) {
                    $visibleSkillTests->push($lockedSet);
                }
                continue;
            }

            $visibleSet = $setResolver->currentSkillSet((int)$groupId, $application)
                ?: $setResolver->nextSkillSet((int)$groupId, $application);

            if ($visibleSet) {
                $visibleSkillTests->push($visibleSet);
            }
        }

        $skillTests = $visibleSkillTests->sortBy('start_date')->values();

        app(\App\Services\Applicant\ApplicationOwnershipService::class)->authorize($request, $application);
        $oldDate = Carbon::parse($application->updated_at);
        $diffInDays = $oldDate->diffInDays(now());
        return view('guest.applications.show', compact('application', 'applicationInquiries', 'diffInDays', 'exams', 'skillTests'));
    }

    public function store(Request $request, Vacancy $vacancy)
    {
        return redirect()->route('applicant.apply.review', $vacancy);
    }

    public function inquire(Application $application, Request $request)
    {
        app(\App\Services\Applicant\ApplicationOwnershipService::class)->authorize($request, $application);
        $data = $request->validate([
            'message' => 'required'
        ]);

        $newInquiry = Inquiry::create([
            'application_id' => $application->id,
            'author' => $application->getFullname(),
            'message' => $data['message'],
            'status' => 1,
        ]);
        
        return redirect(route('guest.applications.show', ['application' => $application]))->with('status_inquiry', 'Inquiry message was successfully sent.');
    }
}
