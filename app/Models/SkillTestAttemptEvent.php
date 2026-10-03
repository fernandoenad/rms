<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillTestAttemptEvent extends Model
{
    protected $fillable = [
        'skill_test_attempt_id','event_type','event_at',
        'ip_address','user_agent','metadata'
    ];

    protected $casts = [
        'event_at'=>'datetime',
        'metadata'=>'array',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(SkillTestAttempt::class, 'skill_test_attempt_id');
    }
}
