<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillTest extends Model
{
    protected $fillable = [
        'vacancy_id','title','code','task_version','supersedes_skill_test_id',
        'review_status','reviewed_by','reviewed_at','review_notes',
        'instructions','expected_output','start_date','end_date',
        'duration','access_mode','submission_modes','allowed_extensions','max_file_size_kb',
        'ai_scoring','score_release_policy','scores_released_at','status'
    ];

    protected $casts = [
        'start_date'=>'datetime','end_date'=>'datetime','submission_modes'=>'array',
        'allowed_extensions'=>'array','ai_scoring'=>'boolean',
        'reviewed_at'=>'datetime','scores_released_at'=>'datetime'
    ];

    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
    public function rubricCriteria(): HasMany { return $this->hasMany(SkillTestRubricCriterion::class)->orderBy('sort_order'); }
    public function attempts(): HasMany { return $this->hasMany(SkillTestAttempt::class); }
    public function assignments(): HasMany { return $this->hasMany(SkillTestAssignment::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function supersedes(): BelongsTo { return $this->belongsTo(SkillTest::class, 'supersedes_skill_test_id'); }

    public function scoresAreReleased(): bool
    {
        if ($this->score_release_policy === 'hidden') return false;
        if ($this->score_release_policy === 'immediate') return true;
        if ($this->score_release_policy === 'after_close') {
            return (bool) $this->end_date && now()->gte($this->end_date);
        }

        return $this->scores_released_at !== null;
    }
}

