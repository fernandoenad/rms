<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentAttemptEvent extends Model
{
    protected $fillable = [
        'exam_attempt_id', 'event_type', 'event_at',
        'ip_address', 'user_agent', 'metadata',
    ];

    protected $casts = [
        'event_at' => 'datetime',
        'metadata' => 'array',
    ];
}
