<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WrittenExamOption extends Model
{
    protected $fillable = ['written_exam_id', 'option_text', 'is_correct', 'source_position'];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(WrittenExam::class, 'written_exam_id');
    }
}
