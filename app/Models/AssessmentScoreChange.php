<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentScoreChange extends Model
{
    protected $fillable = [
        'exam_attempt_id','skill_test_attempt_id','previous_score','new_score',
        'source','reason','changed_by'
    ];

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
