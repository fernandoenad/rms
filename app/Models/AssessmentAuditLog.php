<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentAuditLog extends Model
{
    protected $fillable = [
        'assessment_group_id','exam_id','written_exam_id','user_id','action','metadata'
    ];

    protected $casts = ['metadata' => 'array'];
}
