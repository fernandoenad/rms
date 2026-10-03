<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentGroupAttemptLock extends Model
{
    protected $fillable = [
        'assessment_group_id',
        'application_id',
        'exam_id',
        'exam_attempt_id',
    ];

    public function assessmentGroup(): BelongsTo
    {
        return $this->belongsTo(AssessmentGroup::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }
}
