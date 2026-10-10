<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AssessmentGovernanceService;
use App\Services\AssessmentIntegrityService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AssessmentIntegrityController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, AssessmentIntegrityService $integrity)
    {
        $data = $request->validate([
            'type' => 'nullable|in:written,skills',
            'q' => 'nullable|string|max:150',
        ]);

        $type = $data['type'] ?? null;
        $search = trim((string) ($data['q'] ?? ''));

        $rows = $integrity->anomalies($type, $search ?: null);

        $summary = [
            'total' => $rows->count(),
            'written' => $rows->where('type', 'written')->count(),
            'skills' => $rows->where('type', 'skills')->count(),
            'modifier_captured' => $rows->where('modifier_captured', true)->count(),
        ];

        $perPage = 50;
        $page = max(1, (int) $request->input('page', 1));
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('admin.assessment_center.integrity', [
            'rows' => $paginator,
            'summary' => $summary,
            'type' => $type,
            'search' => $search,
        ]);
    }

    public function repair(
        Request $request,
        AssessmentIntegrityService $integrity,
        AssessmentGovernanceService $governance
    ) {
        $data = $request->validate([
            'type' => 'required|in:written,skills',
            'source_id' => 'required|integer|min:1',
        ]);

        $anomaly = $integrity->anomalies($data['type'])
            ->first(fn ($row) => (int) $row['source_id'] === (int) $data['source_id']);

        if (!$anomaly) {
            return back()->with('status', 'No current integrity anomaly was found for that score. It may already have been corrected.');
        }

        $ok = $integrity->repair($data['type'], (int) $data['source_id']);

        if (!$ok) {
            return back()->with('status', 'RMS could not repair this score because the authoritative score is not currently eligible for synchronization.');
        }

        $governance->log('assessment_score_integrity_repaired', [
            'assessment_group_id' => $anomaly['assessment_group_id'] ?? null,
            'exam_id' => $anomaly['exam_id'] ?? null,
            'skill_test_id' => $anomaly['skill_test_id'] ?? null,
        ], [
            'application_id' => (int) $anomaly['application']->id,
            'assessment_id' => (int) $anomaly['assessment_id'],
            'criterion' => $anomaly['criterion'],
            'assessment_score_before' => $anomaly['current'],
            'assessment_center_score' => $anomaly['expected'],
            'source_type' => $anomaly['type'],
            'source_attempt_id' => (int) $anomaly['source_id'],
            'last_recorded_modifier' => $anomaly['modifier'],
            'last_recorded_modifier_at' => optional($anomaly['modifier_at'])->toIso8601String(),
            'reason' => 'Forced assessment criterion back to the authoritative RMS Assessment Center score after integrity mismatch detection.',
        ]);

        return back()->with(
            'status',
            'Integrity anomaly repaired. The applicant assessment now uses the authoritative RMS Assessment Center score.'
        );
    }
}
