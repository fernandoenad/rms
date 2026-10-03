<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentAccommodation extends Model
{
    protected $fillable = [
        'application_id','assessment_group_id','exam_id','skill_test_id',
        'extra_minutes','large_text','notes','approved_by'
    ];

    protected $casts = ['large_text'=>'boolean'];
}
