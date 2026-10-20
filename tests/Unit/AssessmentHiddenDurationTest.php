<?php

namespace Tests\Unit;

use App\Support\AssessmentHiddenDuration;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AssessmentHiddenDurationTest extends TestCase
{
    private function event(string $type, string $time): object
    {
        return (object) [
            'event_type' => $type,
            'event_at' => CarbonImmutable::parse($time, 'Asia/Manila'),
        ];
    }

    public function test_it_sums_multiple_completed_intervals(): void
    {
        $result = AssessmentHiddenDuration::summarize([
            $this->event('tab_hidden', '2026-10-20 09:00:00'),
            $this->event('tab_visible', '2026-10-20 09:00:08'),
            $this->event('tab_hidden', '2026-10-20 09:03:00'),
            $this->event('tab_visible', '2026-10-20 09:04:12'),
        ]);

        $this->assertSame(80, $result['seconds']);
        $this->assertSame('00:01:20', $result['formatted']);
        $this->assertSame(0, $result['unpaired_hidden_intervals']);
    }

    public function test_it_flags_a_hidden_interval_without_a_visible_event(): void
    {
        $result = AssessmentHiddenDuration::summarize([
            $this->event('tab_hidden', '2026-10-20 09:00:00'),
            $this->event('tab_visible', '2026-10-20 09:00:05'),
            $this->event('tab_hidden', '2026-10-20 09:07:00'),
        ]);

        $this->assertSame(5, $result['seconds']);
        $this->assertSame(1, $result['unpaired_hidden_intervals']);
    }

    public function test_it_ignores_unmatched_visible_and_duplicate_hidden_events(): void
    {
        $result = AssessmentHiddenDuration::summarize([
            $this->event('tab_visible', '2026-10-20 09:00:00'),
            $this->event('tab_hidden', '2026-10-20 09:00:01'),
            $this->event('tab_hidden', '2026-10-20 09:00:02'),
            $this->event('tab_visible', '2026-10-20 09:00:11'),
            $this->event('tab_visible', '2026-10-20 09:00:15'),
        ]);

        $this->assertSame(10, $result['seconds']);
        $this->assertSame(0, $result['unpaired_hidden_intervals']);
    }

    public function test_it_returns_zero_with_no_hidden_events(): void
    {
        $result = AssessmentHiddenDuration::summarize([]);

        $this->assertSame(0, $result['seconds']);
        $this->assertSame('00:00:00', $result['formatted']);
        $this->assertSame(0, $result['unpaired_hidden_intervals']);
    }
}
