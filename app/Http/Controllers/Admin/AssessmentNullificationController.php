<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AssessmentGroup;
use App\Models\AssessmentNullificationBatch;
use App\Models\SkillTestGroup;
use App\Models\Vacancy;
use App\Services\AssessmentNullificationService;
use Illuminate\Http\Request;

class AssessmentNullificationController extends Controller
{
    protected function authorizeHr(): void
    {
        $role = auth()->user()?->role;
        abort_unless($role && (int) $role->level === 1 && (int) $role->status === 1, 403);
    }

    public function index(Request $request, AssessmentNullificationService $service)
    {
        $this->authorizeHr();
        $data = $request->validate([
            'vacancy_id' => 'nullable|integer|exists:vacancies,id',
            'written_group_id' => 'nullable|integer|exists:assessment_groups,id',
            'skills_group_id' => 'nullable|integer|exists:skill_test_groups,id',
        ]);

        $preview = null;
        $vacancyId = (int) ($data['vacancy_id'] ?? 0);
        $writtenId = !empty($data['written_group_id']) ? (int) $data['written_group_id'] : null;
        $skillsId = !empty($data['skills_group_id']) ? (int) $data['skills_group_id'] : null;
        if ($vacancyId && ($writtenId || $skillsId)) {
            $preview = $service->preview($vacancyId, $writtenId, $skillsId);
        }

        return view('admin.assessment_center.nullifications', [
            'vacancies' => Vacancy::orderByDesc('id')->get(['id', 'position_title', 'cycle']),
            'writtenGroups' => AssessmentGroup::orderByDesc('id')->get(['id', 'vacancy_id', 'title', 'assessment_score_key']),
            'skillsGroups' => SkillTestGroup::orderByDesc('id')->get(['id', 'vacancy_id', 'title', 'assessment_score_key']),
            'batches' => AssessmentNullificationBatch::with(['vacancy:id,position_title'])
                ->withCount('items')->latest('id')->paginate(20),
            'vacancyId' => $vacancyId,
            'writtenId' => $writtenId,
            'skillsId' => $skillsId,
            'preview' => $preview,
        ]);
    }

    public function store(
        Request $request,
        AssessmentNullificationService $service
    ) {
        $this->authorizeHr();
        $data = $request->validate([
            'vacancy_id' => 'required|integer|exists:vacancies,id',
            'written_group_id' => 'nullable|integer|exists:assessment_groups,id',
            'skills_group_id' => 'nullable|integer|exists:skill_test_groups,id',
            'board_reference' => 'required|string|min:4|max:255',
            'board_date' => 'required|date|before_or_equal:today',
            'reason' => 'required|string|min:20|max:5000',
            'scope_fingerprint' => 'required|string|size:64|regex:/^[a-f0-9]{64}$/',
            'confirmation' => 'required|in:NULLIFY',
        ]);
        $batch = $service->nullify($data, (int) auth()->id());
        return redirect()->route('admin.assessment_nullifications.index')
            ->with('status', 'Board-authorized nullification #'.$batch->id.' applied. Original assessment evidence is preserved.');
    }

    public function restore(
        Request $request,
        AssessmentNullificationBatch $batch,
        AssessmentNullificationService $service
    ) {
        $this->authorizeHr();
        $data = $request->validate([
            'restoration_reference' => 'required|string|min:4|max:255',
            'restoration_reason' => 'required|string|min:20|max:5000',
            'confirmation' => 'required|in:RESTORE',
        ]);

        $service->restore($batch, (int) auth()->id(), $data['restoration_reference'], $data['restoration_reason']);
        return back()->with('status', 'Nullification #'.$batch->id.' reversed by authorized administrative action. Assessment groups remain paused.');
    }

    public function export(AssessmentNullificationBatch $batch)
    {
        $this->authorizeHr();
        $filename = 'board-nullification-'.$batch->id.'-audit.csv';
        return response()->streamDownload(function () use ($batch) {
            $handle = fopen('php://output', 'w');
            // Spreadsheet applications may interpret a leading =, +, -, or @
            // as a formula. Protect free-text values in downloaded audit CSVs.
            $safe = static function ($value) {
                return is_string($value) && preg_match('/^[\\x00-\\x20]*[=+\\-@]/', $value)
                    ? "'".$value
                    : $value;
            };
            fputcsv($handle, [
                'Batch', 'Board Reference', 'Status', 'Application Code', 'Criterion',
                'Original Value (JSON)', 'Originally Present', 'Current Official Value', 'Nullified At',
            ]);

            $batch->items()->with('application:id,application_code')->orderBy('id')
                ->chunkById(250, function ($items) use ($handle, $batch, $safe) {
                    foreach ($items as $item) {
                        $assessment = \App\Models\Assessment::where('application_id', $item->application_id)->first();
                        $values = json_decode((string) $assessment?->assessment, true);
                        fputcsv($handle, array_map($safe, [
                            $batch->id, $batch->board_reference, $batch->status,
                            $item->application?->application_code,
                            $item->criterion_key, $item->previous_value_json,
                            $item->previously_present ? 'Yes' : 'No',
                            is_array($values) ? ($values[$item->criterion_key] ?? '') : '',
                            $batch->created_at?->toIso8601String(),
                        ]));
                    }
                });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
