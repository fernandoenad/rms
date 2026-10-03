<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentIncident extends Model
{
    protected $fillable = [
        'assessment_group_id','exam_attempt_id','application_id','type','notes','status',
        'created_by','resolved_by','resolved_at'
    ];

    protected $casts = ['resolved_at' => 'datetime'];
}
