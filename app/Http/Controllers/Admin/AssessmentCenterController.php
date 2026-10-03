<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentContentBank;
use App\Models\AssessmentGroup;
use App\Models\AssessmentPerformanceSample;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\SkillTest;
use App\Models\SkillTestAttempt;
use App\Models\SkillTestAiEvaluation;
use App\Models\WrittenExam;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssessmentCenterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
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

        $groups = AssessmentGroup::with([
                'vacancy:id,position_title',
                'exams:id,assessment_group_id,status,is_paused,archived_at',
            ])
            ->whereNull('archived_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $skillTests = SkillTest::with('vacancy:id,position_title')
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
            'skillTests',
            'standaloneExams'
        ));
    }
}
