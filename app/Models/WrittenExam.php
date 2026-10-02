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
        'answer_key', 'rationale', 'ai_generated', 'solo_level', 'difficulty', 'competency_basis', 'attempts', 'status',
    ];

    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function options(): HasMany { return $this->hasMany(WrittenExamOption::class); }
}
