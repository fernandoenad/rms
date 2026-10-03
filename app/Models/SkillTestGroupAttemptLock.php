<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillTestGroupAttemptLock extends Model
{
    protected $fillable = [
        'skill_test_group_id','application_id','skill_test_id','skill_test_attempt_id'
    ];

    public function group(): BelongsTo { return $this->belongsTo(SkillTestGroup::class, 'skill_test_group_id'); }
    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function skillTest(): BelongsTo { return $this->belongsTo(SkillTest::class); }
    public function attempt(): BelongsTo { return $this->belongsTo(SkillTestAttempt::class, 'skill_test_attempt_id'); }
}
