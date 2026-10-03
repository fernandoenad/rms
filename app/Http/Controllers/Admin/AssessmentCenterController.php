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
        $written = [
            'groups' => AssessmentGroup::count(),
            'active_groups' => AssessmentGroup::where('status',1)->whereNull('archived_at')->count(),
            'paused_groups' => AssessmentGroup::where('is_paused',true)->whereNull('archived_at')->count(),
            'sets' => Exam::whereNull('archived_at')->count(),
            'taking_now' => ExamAttempt::where('status',1)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at','>',now());
                })->count(),
            'awaiting_timeout' => ExamAttempt::where('status',1)
                ->whereNotNull('expires_at')->where('expires_at','<=',now())->count(),
            'submitted' => ExamAttempt::where('status',2)->count(),
            'pending_item_review' => WrittenExam::where('status',1)
                ->where('review_status','!=','approved')->count(),
        ];

        $skills = [
            'tests' => SkillTest::whereNull('archived_at')->count(),
            'published' => SkillTest::where('status',1)->whereNull('archived_at')->count(),
            'paused' => SkillTest::where('is_paused',true)->whereNull('archived_at')->count(),
            'taking_now' => SkillTestAttempt::where('status',1)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at','>',now());
                })->count(),
            'awaiting_timeout' => SkillTestAttempt::where('status',1)
                ->whereNotNull('expires_at')->where('expires_at','<=',now())->count(),
            'submitted' => SkillTestAttempt::where('status',2)->count(),
            'pending_human' => SkillTestAttempt::where('status',2)->whereNull('final_score')->count(),
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
            ->orderBy('latency_ms')
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
            'heartbeat',
            'schedulerHealthy',
            'groups',
            'skillTests',
            'standaloneExams'
        ));
    }
}
