<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillTestHumanScore extends Model
{
    protected $fillable = [
        'skill_test_attempt_id',
        'skill_test_rubric_criterion_id',
        'score',
        'notes',
        'evaluator_id',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(SkillTestAttempt::class, 'skill_test_attempt_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(SkillTestRubricCriterion::class, 'skill_test_rubric_criterion_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
