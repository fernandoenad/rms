<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentGroup extends Model
{
    protected $fillable = ['vacancy_id', 'title', 'code', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function attemptLocks(): HasMany
    {
        return $this->hasMany(AssessmentGroupAttemptLock::class);
    }
}
