<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentAiGenerationRun extends Model
{
    protected $fillable = [
        'exam_id','requested_count','generated_count','failed_batches','batch_count',
        'completed_batches','solo_distribution','context_options','status','last_error','requested_by'
    ];

    protected $casts = [
        'solo_distribution' => 'array',
        'context_options' => 'array',
    ];
}
