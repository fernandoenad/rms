<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttemptItemOrder extends Model
{
    protected $fillable = ['exam_attempt_id', 'written_exam_id', 'option_order'];

    protected $casts = [
        'option_order' => 'array',
    ];
}
