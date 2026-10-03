<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillTestGroup extends Model
{
    protected $fillable = [
        'vacancy_id','title','code','expected_sets','status',
        'score_release_policy','assessment_score_key','scores_released_at','scores_synced_at',
        'is_paused','pause_reason','paused_at','paused_by','archived_at','archived_by',
    ];

    protected $casts = [
        'status'=>'boolean','scores_released_at'=>'datetime','scores_synced_at'=>'datetime',
        'is_paused'=>'boolean','paused_at'=>'datetime','archived_at'=>'datetime',
    ];

    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
    public function skillTests(): HasMany { return $this->hasMany(SkillTest::class); }
    public function attemptLocks(): HasMany { return $this->hasMany(SkillTestGroupAttemptLock::class); }

    public function scoresAreReleased(): bool
    {
        if ($this->score_release_policy === 'hidden') return false;
        if ($this->score_release_policy === 'immediate') return true;

        if ($this->score_release_policy === 'after_close') {
            $published = $this->skillTests()->where('status',1)->get(['end_date']);
            return $published->isNotEmpty()
                && $published->every(fn ($set) => $set->end_date && now()->gte($set->end_date));
        }

        return $this->scores_released_at !== null;
    }
}
