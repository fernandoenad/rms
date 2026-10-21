<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicantIntegrityFlag extends Model
{
    protected $fillable = [
        'application_id','vacancy_id','rule_set_id','source_type','source_attempt_id',
        'signals','metrics','status','created_by','reviewed_by','review_reason','reviewed_at',
    ];
    protected $casts = [
        'signals' => 'array', 'metrics' => 'array', 'reviewed_at' => 'datetime',
    ];
    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function ruleSet(): BelongsTo { return $this->belongsTo(IntegrityRuleSet::class); }
    public function actions(): HasMany { return $this->hasMany(ApplicantIntegrityFlagAction::class, 'flag_id'); }
}
