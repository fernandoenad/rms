<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrityRuleSet extends Model
{
    protected $fillable = ['vacancy_id','version','switch_count','total_hidden_seconds',
        'average_hidden_seconds','longest_hidden_seconds','is_active','authority_reference','created_by'];

    protected $casts = ['is_active' => 'boolean'];
    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
}
