<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentNullificationBatch extends Model
{
    protected $fillable = [
        'vacancy_id', 'written_group_id', 'skills_group_id', 'board_reference',
        'board_date', 'reason', 'status', 'created_by', 'restored_by',
        'restoration_reference', 'restoration_reason', 'restored_at',
    ];

    protected $casts = ['board_date' => 'date', 'restored_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentNullificationItem::class, 'batch_id');
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function writtenGroup(): BelongsTo
    {
        return $this->belongsTo(AssessmentGroup::class, 'written_group_id');
    }

    public function skillsGroup(): BelongsTo
    {
        return $this->belongsTo(SkillTestGroup::class, 'skills_group_id');
    }
}
