<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillTest extends Model
{
    protected $fillable = [
        'vacancy_id','title','code','instructions','expected_output','start_date','end_date',
        'duration','access_mode','submission_modes','allowed_extensions','max_file_size_kb',
        'ai_scoring','status'
    ];

    protected $casts = [
        'start_date'=>'datetime','end_date'=>'datetime','submission_modes'=>'array',
        'allowed_extensions'=>'array','ai_scoring'=>'boolean'
    ];

    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
    public function rubricCriteria(): HasMany { return $this->hasMany(SkillTestRubricCriterion::class)->orderBy('sort_order'); }
    public function attempts(): HasMany { return $this->hasMany(SkillTestAttempt::class); }
}
