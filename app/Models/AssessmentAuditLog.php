<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAuditLog extends Model
{
    protected $fillable = [
        'assessment_group_id','exam_id','written_exam_id',
        'skill_test_id','skill_test_rubric_criterion_id',
        'user_id','action','metadata'
    ];

    protected $casts = ['metadata' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

