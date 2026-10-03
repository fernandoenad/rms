<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentSnapshot extends Model
{
    protected $fillable = [
        'target_type','assessment_group_id','exam_id','skill_test_id',
        'event','snapshot','created_by'
    ];

    protected $casts = ['snapshot'=>'array'];
}
