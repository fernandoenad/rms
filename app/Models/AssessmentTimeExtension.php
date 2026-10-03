<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentTimeExtension extends Model
{
    protected $fillable = [
        'exam_attempt_id','skill_test_attempt_id','minutes','reason','created_by'
    ];

    public function examAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class);
    }

    public function skillTestAttempt(): BelongsTo
    {
        return $this->belongsTo(SkillTestAttempt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
