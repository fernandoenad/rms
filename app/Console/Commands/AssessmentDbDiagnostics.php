<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssessmentDbDiagnostics extends Command
{
    protected $signature = 'assessments:db-diagnostics {--explain : Run representative EXPLAIN queries on MySQL/MariaDB}';
    protected $description = 'Inspect Assessment Center high-volume indexes and representative query plans.';

    protected array $requiredIndexes = [
        'applications' => [
            'applications_email_idx',
            'applications_vacancy_email_idx',
            'applications_vacancy_code_idx',
        ],
        'assessments' => [
            'assessments_application_idx',
        ],
        'exams' => [
            'exams_vac_status_start_idx',
            'exams_group_status_start_idx',
        ],
        'written_exams' => [
            'written_exam_status_review_idx',
            'written_status_review_idx',
        ],
        'exam_attempts' => [
            'exam_attempts_status_expires_idx',
            'exam_attempts_status_started_idx',
            'exam_attempts_exam_status_started_idx',
            'exam_attempts_exam_status_expires_idx',
        ],
        'exam_attempt_answers' => [
            'exam_answers_updated_attempt_idx',
        ],
        'assessment_group_attempt_locks' => [
            'group_locks_application_group_idx',
        ],
        'skill_tests' => [
            'skill_tests_vac_status_start_idx',
            'skill_tests_status_archive_idx',
        ],
        'skill_test_attempts' => [
            'skill_attempts_status_expires_global_idx',
            'skill_attempts_status_final_idx',
            'skill_attempts_status_started_idx',
            'skill_attempts_test_status_expires_idx',
        ],
        'skill_test_submissions' => [
            'skill_submissions_attempt_version_idx',
        ],
        'skill_test_ai_evaluations' => [
            'skill_ai_status_created_idx',
            'skill_ai_attempt_status_idx',
        ],
    ];

    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();
        $this->info('Database driver: '.$driver);

        if (!in_array($driver, ['mysql','mariadb'], true)) {
            $this->warn('Index-name inspection and EXPLAIN output are currently implemented for MySQL/MariaDB.');
            return self::SUCCESS;
        }

        $missing = [];

        foreach ($this->requiredIndexes as $table => $required) {
            if (!Schema::hasTable($table)) {
                $this->warn($table.': table missing');
                continue;
            }

            $rows = DB::select('SHOW INDEX FROM `'.$table.'`');
            $present = collect($rows)->pluck('Key_name')->unique()->values()->all();
            $tableMissing = array_values(array_diff($required, $present));

            if ($tableMissing) {
                foreach ($tableMissing as $index) {
                    $missing[] = $table.'.'.$index;
                }
                $this->error($table.': missing '.implode(', ', $tableMissing));
            } else {
                $this->line($table.': OK');
            }
        }

        if ($missing) {
            $this->newLine();
            $this->warn(count($missing).' expected high-volume index(es) are missing. Run pending migrations before load testing.');
        } else {
            $this->newLine();
            $this->info('All expected Assessment Center high-volume indexes are present.');
        }

        if ($this->option('explain')) {
            $this->runExplainSamples();
        }

        return $missing ? self::FAILURE : self::SUCCESS;
    }

    protected function runExplainSamples(): void
    {
        $this->newLine();
        $this->info('Representative EXPLAIN plans');

        $samples = [
            'Written timeout scan' => [
                'SELECT id FROM exam_attempts WHERE status = 1 AND expires_at IS NOT NULL AND expires_at <= NOW() ORDER BY id LIMIT 500',
                [],
            ],
            'Skills timeout scan' => [
                'SELECT id FROM skill_test_attempts WHERE status = 1 AND expires_at IS NOT NULL AND expires_at <= NOW() ORDER BY id LIMIT 500',
                [],
            ],
            'Pending skills human evaluation' => [
                'SELECT id FROM skill_test_attempts WHERE status = 2 AND final_score IS NULL ORDER BY id LIMIT 100',
                [],
            ],
            'Pending skills AI jobs' => [
                "SELECT id FROM skill_test_ai_evaluations WHERE status IN ('pending','processing') ORDER BY id LIMIT 100",
                [],
            ],
        ];

        $groupId = Schema::hasTable('assessment_groups')
            ? DB::table('assessment_groups')->value('id')
            : null;

        if ($groupId) {
            $samples['Recent written answer activity'] = [
                'SELECT COUNT(*) FROM exam_attempt_answers a JOIN exam_attempts t ON t.id = a.exam_attempt_id JOIN exams e ON e.id = t.exam_id WHERE e.assessment_group_id = ? AND a.updated_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)',
                [$groupId],
            ];
        }

        foreach ($samples as $name => [$sql, $bindings]) {
            $this->newLine();
            $this->comment($name);

            try {
                $plan = DB::select('EXPLAIN '.$sql, $bindings);

                $rows = collect($plan)->map(function ($row) {
                    $row = (array) $row;
                    return [
                        'table'=>$row['table'] ?? '',
                        'type'=>$row['type'] ?? '',
                        'key'=>$row['key'] ?? '',
                        'rows'=>$row['rows'] ?? '',
                        'extra'=>$row['Extra'] ?? '',
                    ];
                })->all();

                $this->table(['table','type','key','rows','extra'], $rows);
            } catch (\Throwable $e) {
                $this->warn('EXPLAIN failed: '.$e->getMessage());
            }
        }
    }
}
