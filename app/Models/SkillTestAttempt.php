<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillTestAttempt extends Model
{
    protected $fillable = [
        'skill_test_id','application_id','started_at','expires_at','submitted_at','status',
        'ai_proposed_score','final_score','finalized_by','evaluated_at',
        'voided_at','voided_by','void_reason','retake_skill_test_id'
    ];

    protected $casts = [
        'started_at'=>'datetime','expires_at'=>'datetime','submitted_at'=>'datetime',
        'evaluated_at'=>'datetime','voided_at'=>'datetime'
    ];

    public function skillTest(): BelongsTo { return $this->belongsTo(SkillTest::class); }
    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function submissions(): HasMany { return $this->hasMany(SkillTestSubmission::class); }
    public function aiEvaluations(): HasMany { return $this->hasMany(SkillTestAiEvaluation::class); }
    public function humanScores(): HasMany { return $this->hasMany(SkillTestHumanScore::class); }
    public function events(): HasMany { return $this->hasMany(SkillTestAttemptEvent::class); }
    public function timeExtensions(): HasMany { return $this->hasMany(AssessmentTimeExtension::class); }
    public function scoreChanges(): HasMany { return $this->hasMany(AssessmentScoreChange::class); }
    public function incidents(): HasMany { return $this->hasMany(AssessmentIncident::class, 'skill_test_attempt_id'); }
}

