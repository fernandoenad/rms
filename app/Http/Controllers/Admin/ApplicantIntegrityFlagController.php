<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicantIntegrityFlag;
use App\Models\Application;
use App\Models\IntegrityRuleSet;
use App\Models\Vacancy;
use App\Services\ApplicantIntegrityFlagService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApplicantIntegrityFlagController extends Controller
{
    private function authorizeHr(): void
    {
        $role = auth()->user()?->role;
        abort_unless($role && (int) $role->level === 1 && (int) $role->status === 1, 403);
    }

    public function index(Request $request, ApplicantIntegrityFlagService $service)
    {
        $this->authorizeHr();
        $data = $request->validate([
            'vacancy_id' => 'nullable|integer|exists:vacancies,id',
            'status' => 'nullable|in:pending,confirmed,cleared',
        ]);
        $vacancyId = (int) ($data['vacancy_id'] ?? 0);
        $status = $data['status'] ?? null;
        $query = ApplicantIntegrityFlag::query()->with([
            'application:id,application_code,first_name,middle_name,last_name,vacancy_id',
            'ruleSet:id,version,authority_reference',
            'actions' => fn ($q) => $q->orderByDesc('id'),
        ]);
        if ($vacancyId) {
            $query->where('vacancy_id', $vacancyId);
        } else {
            $query->whereRaw('1 = 0');
        }
        if ($status) $query->where('status', $status);

        return view('admin.assessment_center.applicant_flags', [
            'vacancies' => Vacancy::orderByDesc('id')->get(['id','position_title','cycle']),
            'vacancyId' => $vacancyId,
            'selectedVacancy' => $vacancyId ? Vacancy::find($vacancyId) : null,
            'rule' => $vacancyId ? $service->activeRuleSet($vacancyId) : null,
            'history' => $vacancyId ? IntegrityRuleSet::where('vacancy_id', $vacancyId)->orderByDesc('version')->limit(10)->get() : collect(),
            'status' => $status,
            'flags' => $query->orderByDesc('id')->paginate(30)->withQueryString(),
            'counts' => $vacancyId ? ApplicantIntegrityFlag::where('vacancy_id', $vacancyId)
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status') : collect(),
        ]);
    }

    public function rules(Request $request, ApplicantIntegrityFlagService $service)
    {
        $this->authorizeHr();
        $data = $request->validate([
            'vacancy_id' => 'required|integer|exists:vacancies,id',
            'authority_reference' => 'required|string|min:4|max:255',
            'switch_count' => 'required|integer|min:1|max:10000',
            'total_hidden_seconds' => 'required|integer|min:0|max:86400',
            'average_hidden_seconds' => 'required|integer|min:0|max:86400',
            'longest_hidden_seconds' => 'required|integer|min:0|max:86400',
        ]);
        $rule = $service->createRuleSet((int) $data['vacancy_id'], $data, (int) auth()->id());
        return redirect()->route('admin.integrity_flags.index', ['vacancy_id' => $rule->vacancy_id])
            ->with('status', 'Screening rule set v'.$rule->version.' saved. No applicant was flagged automatically; select Run Screening to generate confidential review cases.');
    }

    public function scan(Request $request, ApplicantIntegrityFlagService $service)
    {
        $this->authorizeHr();
        $data = $request->validate(['rule_set_id'=>'required|integer|exists:integrity_rule_sets,id']);
        $rule = IntegrityRuleSet::findOrFail($data['rule_set_id']);
        $result = $service->scan($rule);
        return redirect()->route('admin.integrity_flags.index', [
            'vacancy_id' => $rule->vacancy_id,
            'status' => 'pending',
        ])->with('status', "Checked {$result['attempts_checked']} completed attempts. Added {$result['review_cases']} review cases; {$result['already_recorded']} previously recorded.");
    }

    public function manual(Request $request, ApplicantIntegrityFlagService $service)
    {
        $this->authorizeHr();
        $data = $request->validate([
            'vacancy_id' => 'required|integer|exists:vacancies,id',
            'application_code' => 'required|string|min:2|max:255',
            'reason' => 'required|string|min:20|max:5000',
        ]);
        $application = Application::where('vacancy_id', $data['vacancy_id'])
            ->where('application_code', trim($data['application_code']))->first();
        if (!$application) {
            throw ValidationException::withMessages(['application_code' => 'No matching applicant application in the selected vacancy.']);
        }
        $service->addManual($application, (int) auth()->id(), $data['reason']);
        return redirect()->route('admin.integrity_flags.index', [
            'vacancy_id' => $application->vacancy_id, 'status' => 'pending',
        ])->with('status', 'Manual referral recorded for confidential review. It will not mark the official RQA unless confirmed.');
    }

    public function decide(Request $request, ApplicantIntegrityFlag $flag, ApplicantIntegrityFlagService $service)
    {
        $this->authorizeHr();
        $data = $request->validate([
            'decision' => 'required|in:confirmed,cleared',
            'reason' => 'required|string|min:20|max:5000',
        ]);
        $service->review($flag, $data['decision'], $data['reason'], (int) auth()->id());
        return redirect()->route('admin.integrity_flags.index', [
            'vacancy_id' => $flag->vacancy_id,
        ])->with('status', $data['decision'] === 'confirmed'
            ? 'Flag confirmed. An asterisk will appear in internal RQA remarks; scores were not changed.'
            : 'Flag cleared. This flag no longer contributes to internal RQA remarks.');
    }

    public function export(Request $request)
    {
        $this->authorizeHr();
        $data = $request->validate([
            'vacancy_id' => 'required|integer|exists:vacancies,id',
        ]);
        $vacancyId = (int) $data['vacancy_id'];
        return response()->streamDownload(function () use ($vacancyId) {
            $h = fopen('php://output','w');
            $safe = static function ($v) {
                if (is_string($v) && preg_match('/^[\\x00-\\x20]*[=+\\-@]/', $v)) return "'".$v;
                return $v;
            };
            fputcsv($h, ['Flag ID','Application Code','Source','Attempt ID','Rule Version',
                'Status','Signals','Metrics JSON','Review Reason','Reviewer User ID','Reviewed At','Created At']);
            ApplicantIntegrityFlag::where('vacancy_id', $vacancyId)
                ->with('application:id,application_code','ruleSet:id,version')
                ->orderBy('id')->chunkById(200, function ($rows) use ($h, $safe) {
                    foreach ($rows as $flag) {
                        fputcsv($h, array_map($safe, [
                            $flag->id, $flag->application?->application_code, $flag->source_type,
                            $flag->source_attempt_id, $flag->ruleSet?->version, $flag->status,
                            implode('; ', $flag->signals ?? []),
                            json_encode($flag->metrics ?? []), $flag->review_reason,
                            $flag->reviewed_by, $flag->reviewed_at?->toIso8601String(),
                            $flag->created_at?->toIso8601String(),
                        ]));
                    }
                });
            fclose($h);
        }, 'confidential-integrity-flags-vacancy-'.$vacancyId.'.csv',
            ['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
