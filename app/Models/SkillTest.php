<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillTest extends Model
{
    protected $fillable = [
        'vacancy_id','skill_test_group_id','created_by','title','code','set_code','task_version','supersedes_skill_test_id',
        'review_status','reviewed_by','reviewed_at','review_notes',
        'instructions','expected_output','start_date','end_date',
        'duration','access_mode','submission_modes','allowed_extensions','max_file_size_kb',
        'ai_scoring','score_release_policy','assessment_score_key','scores_synced_at','scores_released_at',
        'ai_context','ai_generation_focus','ai_use_qualifications','ai_use_job_description',
        'status','approval_status','approved_by','approved_at','approval_notes',
        'is_paused','pause_reason','paused_at','paused_by','archived_at','archived_by'
    ];

    protected $casts = [
        'start_date'=>'datetime','end_date'=>'datetime','submission_modes'=>'array',
        'allowed_extensions'=>'array','ai_scoring'=>'boolean',
        'reviewed_at'=>'datetime','scores_released_at'=>'datetime','scores_synced_at'=>'datetime',
        'ai_use_qualifications'=>'boolean','ai_use_job_description'=>'boolean',
        'approved_at'=>'datetime','is_paused'=>'boolean','paused_at'=>'datetime','archived_at'=>'datetime'
    ];

    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
    public function skillTestGroup(): BelongsTo { return $this->belongsTo(SkillTestGroup::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function rubricCriteria(): HasMany {
        return $this->hasMany(SkillTestRubricCriterion::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
    public function allRubricCriteria(): HasMany {
        return $this->hasMany(SkillTestRubricCriterion::class)->orderBy('sort_order');
    }
    public function attempts(): HasMany { return $this->hasMany(SkillTestAttempt::class); }
    public function assignments(): HasMany { return $this->hasMany(SkillTestAssignment::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function supersedes(): BelongsTo { return $this->belongsTo(SkillTest::class, 'supersedes_skill_test_id'); }

    public function scoresAreReleased(): bool
    {
        if ($this->skill_test_group_id) {
            $this->loadMissing('skillTestGroup');
            return (bool) $this->skillTestGroup?->scoresAreReleased();
        }

        if ($this->score_release_policy === 'hidden') return false;
        if ($this->score_release_policy === 'immediate') return true;
        if ($this->score_release_policy === 'after_close') {
            return (bool) $this->end_date && now()->gte($this->end_date);
        }

        return $this->scores_released_at !== null;
    }
}

