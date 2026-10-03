<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentPerformanceSample extends Model
{
    protected $fillable = [
        'operation','exam_attempt_id','skill_test_attempt_id','latency_ms','recorded_at'
    ];

    protected $casts = ['recorded_at'=>'datetime'];
}
