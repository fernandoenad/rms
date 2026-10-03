<?php

return [
    'performance_sample_retention_days' => (int) env('ASSESSMENT_PERFORMANCE_RETENTION_DAYS', 30),
    'attempt_event_retention_days' => (int) env('ASSESSMENT_EVENT_RETENTION_DAYS', 365),

    // 0 preserves raw AI responses indefinitely. Set a positive value only
    // after the organization adopts an explicit retention policy.
    'ai_raw_response_retention_days' => (int) env('ASSESSMENT_AI_RAW_RETENTION_DAYS', 0),
];
