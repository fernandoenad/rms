<?php

namespace App\Support;

use DateTimeInterface;

final class AssessmentHiddenDuration
{
    /**
     * Sum completed hidden-to-visible intervals from a chronological audit trail.
     *
     * Events must be ordered by event_at then id. Browser event delivery can be
     * delayed or lost, so durations are approximate and unpaired intervals are
     * reported rather than silently counted as zero.
     *
     * @return array{seconds:int,formatted:string,unpaired_hidden_intervals:int,completed_intervals:int,hidden_event_count:int,longest_seconds:int,average_seconds:?float}
     */
    public static function summarize(iterable $orderedEvents): array
    {
        $hiddenAt = null;
        $totalSeconds = 0;
        $completedIntervals = 0;
        $longestSeconds = 0;
        $hiddenEventCount = 0;

        foreach ($orderedEvents as $event) {
            $eventAt = $event->event_at ?? null;
            if (! $eventAt instanceof DateTimeInterface) {
                continue;
            }

            if ($event->event_type === 'tab_hidden') {
                // Count only transitions into hidden state: duplicated browser
                // events must not inflate suspicion frequency.
                if ($hiddenAt === null) {
                    $hiddenEventCount++;
                    $hiddenAt = $eventAt->getTimestamp();
                }
            } elseif ($event->event_type === 'tab_visible' && $hiddenAt !== null) {
                $duration = max(0, $eventAt->getTimestamp() - $hiddenAt);
                $totalSeconds += $duration;
                $longestSeconds = max($longestSeconds, $duration);
                $completedIntervals++;
                $hiddenAt = null;
            }
        }

        return [
            'seconds' => $totalSeconds,
            'completed_intervals' => $completedIntervals,
            'hidden_event_count' => $hiddenEventCount,
            'longest_seconds' => $longestSeconds,
            'average_seconds' => $completedIntervals > 0
                ? round($totalSeconds / $completedIntervals, 2)
                : null,
            'formatted' => sprintf(
                '%02d:%02d:%02d',
                intdiv($totalSeconds, 3600),
                intdiv($totalSeconds % 3600, 60),
                $totalSeconds % 60
            ),
            'unpaired_hidden_intervals' => $hiddenAt === null ? 0 : 1,
        ];
    }
}
