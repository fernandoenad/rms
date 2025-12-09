<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WrittenExam extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'enrollment_key',
        'question',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'answer_key',
        'attempts',
        'status',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
