<?php

namespace App\Services;

use App\Models\ApplicantIntegrityFlag;
use App\Models\ApplicantIntegrityFlagAction;
use App\Models\Application;
use App\Models\AssessmentNullificationItem;
use App\Models\ExamAttempt;
use App\Models\IntegrityRuleSet;
use App\Models\SkillTestAttempt;
use App\Models\Vacancy;
use App\Support\AssessmentHiddenDuration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicantIntegrityFlagService
{
    /**
     * One query per internal RQA render, not one query per applicant row.
     * Raw detections and cleared cases are never printed.
     */
    public function confirmedForVacancy(int $vacancyId): array
    {
        return ApplicantIntegrityFlag::query()
            ->where('vacancy_id', $vacancyId)
            ->where('status', 'confirmed')
            ->distinct()->pluck('application_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    public function activeRuleSet(int $vacancyId): ?IntegrityRuleSet
    {
        return IntegrityRuleSet::where('vacancy_id', $vacancyId)
            ->where('is_active', true)->orderByDesc('version')->first();
    }

    public function createRuleSet(int $vacancyId, array $data, int $actorId): IntegrityRuleSet
    {
        return DB::transaction(function () use ($vacancyId, $data, $actorId) {
            Vacancy::whereKey($vacancyId)->lockForUpdate()->firstOrFail();
            $version = (int) IntegrityRuleSet::where('vacancy_id', $vacancyId)->max('version') + 1;
            IntegrityRuleSet::where('vacancy_id', $vacancyId)->where('is_active', true)
                ->update(['is_active' => false]);
            return IntegrityRuleSet::create([
                'vacancy_id' => $vacancyId,
                'version' => $version,
                'switch_count' => $data['switch_count'],
                'total_hidden_seconds' => $data['total_hidden_seconds'],
                'average_hidden_seconds' => $data['average_hidden_seconds'],
                'longest_hidden_seconds' => $data['longest_hidden_seconds'],
                'authority_reference' => $data['authority_reference'],
                'created_by' => $actorId,
                'is_active' => true,
            ]);
        }, 3);
    }

    /**
     * Explicit, on-demand scan of completed/valid Written and Skills attempts.
     * Screening never changes scores, statuses or existing review decisions.
     * New rule versions can produce new pending reviews without undoing old ones.
     */
    public function scan(IntegrityRuleSet $rule): array
    {
        if (!$rule->is_active) {
            throw ValidationException::withMessages(['rule_set' => 'Activate a current rule set before scanning.']);
        }
        $counts = ['review_cases' => 0, 'already_recorded' => 0, 'attempts_checked' => 0];
        foreach (['written', 'skills'] as $type) {
            $query = $type === 'written'
                ? ExamAttempt::query()->whereHas('exam', fn ($q) => $q->where('vacancy_id', $rule->vacancy_id))
                : SkillTestAttempt::query()->whereHas('skillTest', fn ($q) => $q->where('vacancy_id', $rule->vacancy_id));
            $query->where('status', 2)->whereNull('voided_at')->with([
                'events' => fn ($q) => $q->orderBy('event_at')->orderBy('id'),
            ])->orderBy('id')->chunkById(150, function ($attempts) use ($type, $rule, &$counts) {
                foreach ($attempts as $attempt) {
                    $counts['attempts_checked']++;
                    $application = Application::whereKey($attempt->application_id)
                        ->where('vacancy_id', $rule->vacancy_id)->first();
                    if (!$application) {
                        continue;
                    }
                    $metrics = AssessmentHiddenDuration::summarize($attempt->events);
                    $signals = $this->signals($metrics, $rule);
                    if (!$signals) {
                        continue;
                    }

                    DB::transaction(function () use ($rule, $type, $attempt, $metrics, $signals, &$counts) {
                        $existing = ApplicantIntegrityFlag::where('rule_set_id', $rule->id)
                            ->where('source_type', $type)
                            ->where('source_attempt_id', $attempt->id)->first();
                        if ($existing) {
                            $counts['already_recorded']++;
                            return;
                        }
                        $flag = ApplicantIntegrityFlag::create([
                            'vacancy_id' => $rule->vacancy_id,
                            'application_id' => $attempt->application_id,
                            'rule_set_id' => $rule->id,
                            'source_type' => $type,
                            'source_attempt_id' => $attempt->id,
                            'signals' => $signals,
                            'metrics' => [
                                'switch_count' => $metrics['hidden_event_count'],
                                'completed_intervals' => $metrics['completed_intervals'],
                                'total_hidden_seconds' => $metrics['seconds'],
                                'longest_hidden_seconds' => $metrics['longest_seconds'],
                                'average_hidden_seconds' => $metrics['average_seconds'],
                                'unpaired_hidden_intervals' => $metrics['unpaired_hidden_intervals'],
                            ],
                            'status' => 'pending',
                        ]);
                        $this->audit($flag, 'detected', null, 'pending', 'Automated screening; not evidence of cheating.', null);
                        $counts['review_cases']++;
                    });
                }
            });
        }
        return $counts;
    }

    public function signals(array $metrics, IntegrityRuleSet $rule): array
    {
        $codes = [];
        if ($metrics['hidden_event_count'] >= $rule->switch_count) {
            $codes[] = 'frequent_visibility_events';
        }
        if ($metrics['seconds'] > $rule->total_hidden_seconds) {
            $codes[] = 'extended_total_hidden_time';
        }
        if ($metrics['average_seconds'] !== null && $metrics['average_seconds'] > $rule->average_hidden_seconds) {
            $codes[] = 'extended_average_hidden_time';
        }
        if ($metrics['longest_seconds'] > $rule->longest_hidden_seconds) {
            $codes[] = 'prolonged_single_hidden_interval';
        }
        if ($metrics['unpaired_hidden_intervals'] > 0) {
            $codes[] = 'incomplete_timing_data';
        }
        return $codes;
    }

    public function addManual(Application $application, int $actorId, string $reason): ApplicantIntegrityFlag
    {
        if (ApplicantIntegrityFlag::where('application_id', $application->id)
            ->where('source_type', 'manual')->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['application_id' => 'There is already a pending manual flag for this application.']);
        }
        return DB::transaction(function () use ($application, $actorId, $reason) {
            $flag = ApplicantIntegrityFlag::create([
                'application_id' => $application->id,
                'vacancy_id' => $application->vacancy_id,
                'source_type' => 'manual',
                'status' => 'pending',
                'signals' => ['manual_referral'],
                'created_by' => $actorId,
            ]);
            $this->audit($flag, 'manual_referral', null, 'pending', $reason, $actorId);
            return $flag;
        });
    }

    public function review(ApplicantIntegrityFlag $flag, string $decision, string $reason, int $actorId): void
    {
        DB::transaction(function () use ($flag, $decision, $reason, $actorId) {
            $current = ApplicantIntegrityFlag::whereKey($flag->id)->lockForUpdate()->firstOrFail();
            if ($current->status === $decision) {
                throw ValidationException::withMessages(['decision' => 'The case already has this status.']);
            }
            $from = $current->status;
            $current->update([
                'status' => $decision,
                'review_reason' => $reason,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
            ]);
            $this->audit($current, 'review', $from, $decision, $reason, $actorId);
        }, 3);
    }

    private function audit(ApplicantIntegrityFlag $flag, string $action, ?string $from, string $to, string $reason, ?int $actorId): void
    {
        ApplicantIntegrityFlagAction::create([
            'flag_id' => $flag->id, 'actor_id' => $actorId,
            'action' => $action, 'from_status' => $from,
            'to_status' => $to, 'reason' => $reason, 'created_at' => now(),
        ]);
    }
}
