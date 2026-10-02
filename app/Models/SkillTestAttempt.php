<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillTestAttempt extends Model
{
    protected $fillable = [
        'skill_test_id','application_id','started_at','expires_at','submitted_at','status',
        'ai_proposed_score','final_score','finalized_by','evaluated_at'
    ];

    protected $casts = [
        'started_at'=>'datetime','expires_at'=>'datetime','submitted_at'=>'datetime','evaluated_at'=>'datetime'
    ];

    public function skillTest(): BelongsTo { return $this->belongsTo(SkillTest::class); }
    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function submissions(): HasMany { return $this->hasMany(SkillTestSubmission::class); }
    public function aiEvaluations(): HasMany { return $this->hasMany(SkillTestAiEvaluation::class); }
}
