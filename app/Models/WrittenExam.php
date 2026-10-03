<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WrittenExam extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id', 'enrollment_key', 'question',
        'option_a', 'option_b', 'option_c', 'option_d',
        'answer_key', 'rationale', 'ai_generated', 'solo_level', 'difficulty', 'competency_basis',
        'item_version', 'supersedes_item_id', 'review_status', 'reviewed_by', 'reviewed_at', 'review_notes',
        'attempts', 'status',
    ];

    protected $casts = [
        'ai_generated' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function options(): HasMany { return $this->hasMany(WrittenExamOption::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function supersedes(): BelongsTo { return $this->belongsTo(WrittenExam::class, 'supersedes_item_id'); }
}
