<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentContentBank;
use App\Models\AssessmentGroup;
use App\Models\AssessmentPerformanceSample;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\SkillTest;
use App\Models\SkillTestGroup;
use App\Models\SkillTestAttempt;
use App\Models\SkillTestAiEvaluation;
use App\Models\WrittenExam;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssessmentCenterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function ensureAdmin(): void
    {
        abort_unless((int) optional(optional(auth()->user())->role)->level === 1, 403);
    }

    public function retryFailedJobs()
    {
        $this->ensureAdmin();

        if (!Schema::hasTable('failed_jobs')) {
            return back()->with('status', 'Failed-jobs table is unavailable.');
        }

        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            return back()->with('status', 'There are no failed jobs to retry.');
        }

        Artisan::call('queue:retry', ['id' => ['all']]);

        return back()->with(
            'status',
            $count.' failed job(s) were returned to the queue for retry.'
        );
    }

    public function clearFailedJobs()
    {
        $this->ensureAdmin();

        if (!Schema::hasTable('failed_jobs')) {
            return back()->with('status', 'Failed-jobs table is unavailable.');
        }

        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            return back()->with('status', 'There are no failed jobs to clear.');
        }

        Artisan::call('queue:flush');

        return back()->with(
            'status',
            $count.' failed job record(s) were cleared. This does not cancel jobs that are currently queued or running.'
        );
    }

    public function index()
    {
        $now = now();

        $writtenAttempts = ExamAttempt::query()
            ->selectRaw('SUM(CASE WHEN status = 1 AND (expires_at IS NULL OR expires_at > ?) THEN 1 ELSE 0 END) as taking_now', [$now])
            ->selectRaw('SUM(CASE WHEN status = 1 AND expires_at IS NOT NULL AND expires_at <= ? THEN 1 ELSE 0 END) as awaiting_timeout', [$now])
            ->selectRaw('SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as submitted')
            ->first();

        $writtenGroups = AssessmentGroup::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = 1 AND archived_at IS NULL THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN is_paused = 1 AND archived_at IS NULL THEN 1 ELSE 0 END) as paused')
            ->first();

        $written = [
            'groups' => (int) ($writtenGroups->total ?? 0),
            'active_groups' => (int) ($writtenGroups->active ?? 0),
            'paused_groups' => (int) ($writtenGroups->paused ?? 0),
            'sets' => Exam::whereNull('archived_at')->count(),
            'taking_now' => (int) ($writtenAttempts->taking_now ?? 0),
            'awaiting_timeout' => (int) ($writtenAttempts->awaiting_timeout ?? 0),
            'submitted' => (int) ($writtenAttempts->submitted ?? 0),
            'pending_item_review' => WrittenExam::where('status',1)
                ->where('review_status','!=','approved')->count(),
        ];

        $skillAttempts = SkillTestAttempt::query()
            ->selectRaw('SUM(CASE WHEN status = 1 AND (expires_at IS NULL OR expires_at > ?) THEN 1 ELSE 0 END) as taking_now', [$now])
            ->selectRaw('SUM(CASE WHEN status = 1 AND expires_at IS NOT NULL AND expires_at <= ? THEN 1 ELSE 0 END) as awaiting_timeout', [$now])
            ->selectRaw('SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as submitted')
            ->selectRaw('SUM(CASE WHEN status = 2 AND final_score IS NULL THEN 1 ELSE 0 END) as pending_human')
            ->first();

        $skillStatus = SkillTest::query()
            ->selectRaw('SUM(CASE WHEN archived_at IS NULL THEN 1 ELSE 0 END) as total')
            ->selectRaw('SUM(CASE WHEN status = 1 AND archived_at IS NULL THEN 1 ELSE 0 END) as published')
            ->selectRaw('SUM(CASE WHEN is_paused = 1 AND archived_at IS NULL THEN 1 ELSE 0 END) as paused')
            ->first();

        $skills = [
            'tests' => (int) ($skillStatus->total ?? 0),
            'published' => (int) ($skillStatus->published ?? 0),
            'paused' => (int) ($skillStatus->paused ?? 0),
            'taking_now' => (int) ($skillAttempts->taking_now ?? 0),
            'awaiting_timeout' => (int) ($skillAttempts->awaiting_timeout ?? 0),
            'submitted' => (int) ($skillAttempts->submitted ?? 0),
            'pending_human' => (int) ($skillAttempts->pending_human ?? 0),
            'pending_ai' => SkillTestAiEvaluation::whereIn('status',['pending','processing'])->count(),
        ];

        $queue = [
            'total' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
            'assessment_ai' => Schema::hasTable('jobs')
                ? DB::table('jobs')->where('queue','assessment-ai')->count()
                : null,
            'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
        ];

        $recentSamples = AssessmentPerformanceSample::where('recorded_at','>=',now()->subMinutes(15))
            ->limit(5000)
            ->get(['operation','latency_ms']);

        $performance = [];
        foreach (['written_answer_save','skill_inline_save'] as $operation) {
            $values = $recentSamples->where('operation',$operation)->pluck('latency_ms')->sort()->values();
            $count = $values->count();
            $p95 = $count
                ? $values->get(max(0, (int) ceil($count * 0.95) - 1))
                : null;

            $performance[$operation] = [
                'samples'=>$count,
                'avg_ms'=>$count ? round((float)$values->avg()) : null,
                'p95_ms'=>$p95,
            ];
        }

        $bank = [
            'active'=>AssessmentContentBank::whereNull('retired_at')->count(),
            'retired'=>AssessmentContentBank::whereNotNull('retired_at')->count(),
            'high_exposure'=>AssessmentContentBank::whereNull('retired_at')->where('usage_count','>=',4)->count(),
        ];

        $alerts = [];

        if (($queue['assessment_ai'] ?? 0) >= 500) {
            $alerts[] = ['level'=>'warning','message'=>'Assessment AI queue backlog is 500 or more jobs. Consider adding assessment-ai workers within provider/server limits.'];
        }
        if (($queue['failed'] ?? 0) > 0) {
            $alerts[] = ['level'=>'danger','message'=>'Failed background jobs require review before a high-stakes assessment window.'];
        }
        if (($written['awaiting_timeout'] + $skills['awaiting_timeout']) > 0) {
            $alerts[] = ['level'=>'warning','message'=>'Expired attempts are waiting for finalization. Confirm the scheduler is healthy.'];
        }
        foreach ($performance as $label=>$metrics) {
            if (($metrics['p95_ms'] ?? 0) >= 2000 && ($metrics['samples'] ?? 0) >= 5) {
                $alerts[] = ['level'=>'warning','message'=>str_replace('_',' ',$label).' P95 save latency is at least 2 seconds.'];
            }
        }

        $heartbeatRaw = Cache::get('assessment-center:scheduler-heartbeat');
        $heartbeat = $heartbeatRaw ? now()->parse($heartbeatRaw) : null;
        $schedulerHealthy = $heartbeat && $heartbeat->gte(now()->subMinutes(3));

        $writtenPendingReviewByGroup = WrittenExam::query()
            ->join('exams', 'exams.id', '=', 'written_exams.exam_id')
            ->whereNotNull('exams.assessment_group_id')
            ->where('written_exams.status', 1)
            ->where('written_exams.review_status', '!=', 'approved')
            ->groupBy('exams.assessment_group_id')
            ->selectRaw('exams.assessment_group_id as group_id, COUNT(*) as total')
            ->pluck('total', 'group_id');

        $skillPendingReviewByGroup = SkillTest::query()
            ->whereNotNull('skill_test_group_id')
            ->whereNull('archived_at')
            ->where('review_status', '!=', 'approved')
            ->groupBy('skill_test_group_id')
            ->selectRaw('skill_test_group_id as group_id, COUNT(*) as total')
            ->pluck('total', 'group_id');

        $groups = AssessmentGroup::with([
                'vacancy:id,position_title',
                'exams' => fn ($q) => $q
                    ->select('id','assessment_group_id','status','is_paused','archived_at','start_date','end_date','set_code')
                    ->whereNull('archived_at')
                    ->withCount([
                        'attempts',
                        'attempts as active_attempts_count' => fn ($attempts) => $attempts->where('status',1),
                    ]),
            ])
            ->whereNull('archived_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $skillGroups = SkillTestGroup::with([
                'vacancy:id,position_title',
                'skillTests' => fn ($q) => $q
                    ->select('id','skill_test_group_id','status','is_paused','archived_at','start_date','end_date','set_code')
                    ->whereNull('archived_at')
                    ->withCount([
                        'attempts',
                        'attempts as active_attempts_count' => fn ($attempts) => $attempts->where('status',1),
                    ]),
            ])
            ->whereNull('archived_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $assessmentRows = collect();

        foreach ($groups as $group) {
            $sets = $group->exams;
            $currentSet = $sets
                ->filter(fn ($set) => $set->status
                    && $set->start_date
                    && $set->end_date
                    && $now->gte($set->start_date)
                    && $now->lt($set->end_date))
                ->sortByDesc('start_date')
                ->first();

            $nextSet = $sets
                ->filter(fn ($set) => $set->status && $set->start_date && $now->lt($set->start_date))
                ->sortBy('start_date')
                ->first();

            $published = $sets->where('status', true)->count();
            $scheduled = $sets->filter(fn ($set) => $set->start_date && $set->end_date)->count();
            $pendingReview = (int) ($writtenPendingReviewByGroup[$group->id] ?? 0);
            $issues = collect();
            if ($sets->isEmpty()) $issues->push('No equivalent sets have been created.');
            if ($published < $sets->count()) $issues->push(($sets->count() - $published).' set(s) are still draft.');
            if ($scheduled < $sets->count()) $issues->push(($sets->count() - $scheduled).' set(s) are missing a complete schedule.');
            if ($sets->contains(fn ($set) => (bool)$set->is_paused)) $issues->push('One or more sets are paused.');
            if ($pendingReview > 0) $issues->push($pendingReview.' written item(s) are pending review.');

            $ready = $issues->isEmpty();

            $assessmentRows->push([
                'type' => 'written',
                'type_label' => 'Written',
                'title' => $group->title,
                'position' => optional($group->vacancy)->position_title,
                'sets' => $sets->count(),
                'current_set' => $currentSet?->set_code,
                'next_set' => $nextSet?->set_code,
                'next_at' => $nextSet?->start_date,
                'active_attempts' => (int)$sets->sum('active_attempts_count'),
                'attempts' => (int)$sets->sum('attempts_count'),
                'ready' => $ready,
                'readiness_detail' => $ready
                    ? 'All sets are published, scheduled, and reviewed.'
                    : $issues->first(),
                'readiness_issues' => $issues->values()->all(),
                'state' => $group->is_paused ? 'Paused' : ($group->status ? 'Active' : 'Inactive'),
                'state_class' => $group->is_paused ? 'warning' : ($group->status ? 'success' : 'secondary'),
                'manage_url' => route('admin.assessment_groups.edit',$group),
            ]);
        }

        foreach ($skillGroups as $group) {
            $sets = $group->skillTests;
            $currentSet = $sets
                ->filter(fn ($set) => $set->status
                    && $set->start_date
                    && $set->end_date
                    && $now->gte($set->start_date)
                    && $now->lt($set->end_date))
                ->sortByDesc('start_date')
                ->first();

            $nextSet = $sets
                ->filter(fn ($set) => $set->status && $set->start_date && $now->lt($set->start_date))
                ->sortBy('start_date')
                ->first();

            $published = $sets->where('status', true)->count();
            $scheduled = $sets->filter(fn ($set) => $set->start_date && $set->end_date)->count();
            $pendingReview = (int) ($skillPendingReviewByGroup[$group->id] ?? 0);
            $issues = collect();
            if ($sets->isEmpty()) $issues->push('No equivalent sets have been created.');
            if ($published < $sets->count()) $issues->push(($sets->count() - $published).' set(s) are still draft.');
            if ($scheduled < $sets->count()) $issues->push(($sets->count() - $scheduled).' set(s) are missing a complete schedule.');
            if ($sets->contains(fn ($set) => (bool)$set->is_paused)) $issues->push('One or more sets are paused.');
            if ($pendingReview > 0) $issues->push($pendingReview.' skills task(s) are pending review.');

            $ready = $issues->isEmpty();

            $assessmentRows->push([
                'type' => 'skills',
                'type_label' => 'Skills',
                'title' => $group->title,
                'position' => optional($group->vacancy)->position_title,
                'sets' => $sets->count(),
                'current_set' => $currentSet?->set_code,
                'next_set' => $nextSet?->set_code,
                'next_at' => $nextSet?->start_date,
                'active_attempts' => (int)$sets->sum('active_attempts_count'),
                'attempts' => (int)$sets->sum('attempts_count'),
                'ready' => $ready,
                'readiness_detail' => $ready
                    ? 'All sets are published, scheduled, and reviewed.'
                    : $issues->first(),
                'readiness_issues' => $issues->values()->all(),
                'state' => $group->is_paused ? 'Paused' : ($group->status ? 'Active' : 'Inactive'),
                'state_class' => $group->is_paused ? 'warning' : ($group->status ? 'success' : 'secondary'),
                'manage_url' => route('admin.skill_groups.edit',$group),
            ]);
        }

        $assessmentRows = $assessmentRows
            ->sortBy(fn ($row) => [$row['position'] ?? '', $row['title']])
            ->values();

        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $todayWritten = Exam::with(['assessmentGroup:id,title', 'vacancy:id,position_title'])
            ->whereNull('archived_at')
            ->where('status', 1)
            ->where(function ($q) use ($todayStart, $todayEnd) {
                $q->whereBetween('start_date', [$todayStart, $todayEnd])
                    ->orWhereBetween('end_date', [$todayStart, $todayEnd]);
            })
            ->get(['id','assessment_group_id','vacancy_id','title','set_code','start_date','end_date']);

        $todaySkills = SkillTest::with(['skillTestGroup:id,title', 'vacancy:id,position_title'])
            ->whereNull('archived_at')
            ->where('status', 1)
            ->where(function ($q) use ($todayStart, $todayEnd) {
                $q->whereBetween('start_date', [$todayStart, $todayEnd])
                    ->orWhereBetween('end_date', [$todayStart, $todayEnd]);
            })
            ->get(['id','skill_test_group_id','vacancy_id','title','set_code','start_date','end_date']);

        $todayTimeline = collect();

        foreach ($todayWritten as $set) {
            if ($set->start_date && $set->start_date->between($todayStart, $todayEnd)) {
                $todayTimeline->push([
                    'at'=>$set->start_date,
                    'type'=>'Written',
                    'event'=>'opens',
                    'title'=>$set->assessmentGroup?->title ?: $set->title,
                    'set'=>$set->set_code,
                    'position'=>$set->vacancy?->position_title,
                    'url'=>route('admin.assessments.edit',$set),
                ]);
            }
            if ($set->end_date && $set->end_date->between($todayStart, $todayEnd)) {
                $todayTimeline->push([
                    'at'=>$set->end_date,
                    'type'=>'Written',
                    'event'=>'closes',
                    'title'=>$set->assessmentGroup?->title ?: $set->title,
                    'set'=>$set->set_code,
                    'position'=>$set->vacancy?->position_title,
                    'url'=>route('admin.assessments.edit',$set),
                ]);
            }
        }

        foreach ($todaySkills as $set) {
            if ($set->start_date && $set->start_date->between($todayStart, $todayEnd)) {
                $todayTimeline->push([
                    'at'=>$set->start_date,
                    'type'=>'Skills',
                    'event'=>'opens',
                    'title'=>$set->skillTestGroup?->title ?: $set->title,
                    'set'=>$set->set_code,
                    'position'=>$set->vacancy?->position_title,
                    'url'=>route('admin.skills.edit',$set),
                ]);
            }
            if ($set->end_date && $set->end_date->between($todayStart, $todayEnd)) {
                $todayTimeline->push([
                    'at'=>$set->end_date,
                    'type'=>'Skills',
                    'event'=>'closes',
                    'title'=>$set->skillTestGroup?->title ?: $set->title,
                    'set'=>$set->set_code,
                    'position'=>$set->vacancy?->position_title,
                    'url'=>route('admin.skills.edit',$set),
                ]);
            }
        }

        $todayTimeline = $todayTimeline->sortBy('at')->values();

        $liveWritten = ExamAttempt::with([
                'application:id,application_code',
                'exam:id,title,set_code,assessment_group_id',
                'exam.assessmentGroup:id,title',
            ])
            ->where('status',1)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at','>',$now);
            })
            ->orderByDesc('started_at')
            ->limit(10)
            ->get()
            ->map(fn ($attempt) => [
                'type'=>'Written',
                'application_code'=>$attempt->application?->application_code,
                'assessment'=>$attempt->exam?->assessmentGroup?->title ?: $attempt->exam?->title,
                'set'=>$attempt->exam?->set_code,
                'started_at'=>$attempt->started_at,
                'expires_at'=>$attempt->expires_at,
                'url'=>$attempt->exam ? route('admin.assessments.results',$attempt->exam) : null,
            ]);

        $liveSkills = SkillTestAttempt::with([
                'application:id,application_code',
                'skillTest:id,title,set_code,skill_test_group_id',
                'skillTest.skillTestGroup:id,title',
            ])
            ->where('status',1)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at','>',$now);
            })
            ->orderByDesc('started_at')
            ->limit(10)
            ->get()
            ->map(fn ($attempt) => [
                'type'=>'Skills',
                'application_code'=>$attempt->application?->application_code,
                'assessment'=>$attempt->skillTest?->skillTestGroup?->title ?: $attempt->skillTest?->title,
                'set'=>$attempt->skillTest?->set_code,
                'started_at'=>$attempt->started_at,
                'expires_at'=>$attempt->expires_at,
                'url'=>$attempt->skillTest ? route('admin.skills.results',$attempt->skillTest) : null,
            ]);

        $liveSessions = $liveWritten
            ->concat($liveSkills)
            ->sortByDesc('started_at')
            ->take(15)
            ->values();

        $reviewQueue = collect([
            [
                'label'=>'Written items pending review',
                'count'=>(int)$written['pending_item_review'],
                'icon'=>'fas fa-list-check',
                'type'=>'Written',
                'url'=>route('admin.assessment_center.index').'#assessments',
            ],
            [
                'label'=>'Skills tasks pending review',
                'count'=>(int)$skillPendingReviewByGroup->sum(),
                'icon'=>'fas fa-tools',
                'type'=>'Skills',
                'url'=>route('admin.assessment_center.index').'#assessments',
            ],
            [
                'label'=>'Skills submissions awaiting human evaluation',
                'count'=>(int)$skills['pending_human'],
                'icon'=>'fas fa-user-check',
                'type'=>'Evaluation',
                'url'=>route('admin.skill_groups.index'),
            ],
        ])->filter(fn ($item) => $item['count'] > 0)->values();

        $commandStats = [
            'open_now'=>$assessmentRows->whereNotNull('current_set')->count(),
            'starting_today'=>$todayTimeline->where('event','opens')->count(),
            'closing_today'=>$todayTimeline->where('event','closes')->count(),
            'needs_attention'=>$assessmentRows->where('ready',false)->count(),
            'taking_now'=>(int)$written['taking_now'] + (int)$skills['taking_now'],
            'pending_review'=>(int)$written['pending_item_review'] + (int)$skillPendingReviewByGroup->sum(),
            'pending_evaluation'=>(int)$skills['pending_human'],
        ];

        $standaloneSkillTests = SkillTest::with('vacancy:id,position_title')
            ->whereNull('skill_test_group_id')
            ->whereNull('archived_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $standaloneExams = Exam::with('vacancy:id,position_title')
            ->whereNull('assessment_group_id')
            ->whereNull('archived_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('admin.assessment_center.index', compact(
            'written',
            'skills',
            'queue',
            'performance',
            'bank',
            'alerts',
            'heartbeat',
            'schedulerHealthy',
            'groups',
            'skillGroups',
            'assessmentRows',
            'commandStats',
            'todayTimeline',
            'liveSessions',
            'reviewQueue',
            'standaloneSkillTests',
            'standaloneExams'
        ));
    }
}
