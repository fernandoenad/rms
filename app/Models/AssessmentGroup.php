<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentGroup extends Model
{
    protected $fillable = [
        'vacancy_id', 'title', 'code', 'expected_sets', 'blueprint', 'blueprint_version',
        'status', 'score_release_policy', 'scores_released_at'
    ];

    protected $casts = [
        'status' => 'boolean',
        'blueprint' => 'array',
        'scores_released_at' => 'datetime',
    ];

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

    public function incidents(): HasMany
    {
        return $this->hasMany(AssessmentIncident::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AssessmentAuditLog::class);
    }

    public function scoresAreReleased(): bool
    {
        if ($this->score_release_policy === 'hidden') {
            return false;
        }

        if ($this->score_release_policy === 'immediate') {
            return true;
        }

        if ($this->score_release_policy === 'after_close') {
            $published = $this->exams()->where('status', 1);

            if (!(clone $published)->exists()) {
                return false;
            }

            return !(clone $published)->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>', now());
            })->exists();
        }

        return $this->scores_released_at !== null;
    }
}

