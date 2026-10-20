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
     * @return array{seconds:int,formatted:string,unpaired_hidden_intervals:int}
     */
    public static function summarize(iterable $orderedEvents): array
    {
        $hiddenAt = null;
        $totalSeconds = 0;

        foreach ($orderedEvents as $event) {
            $eventAt = $event->event_at ?? null;
            if (! $eventAt instanceof DateTimeInterface) {
                continue;
            }

            if ($event->event_type === 'tab_hidden') {
                // Duplicate hidden events do not start overlapping intervals.
                $hiddenAt ??= $eventAt->getTimestamp();
            } elseif ($event->event_type === 'tab_visible' && $hiddenAt !== null) {
                $totalSeconds += max(0, $eventAt->getTimestamp() - $hiddenAt);
                $hiddenAt = null;
            }
        }

        return [
            'seconds' => $totalSeconds,
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
