<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentContentBank extends Model
{
    protected $table = 'assessment_content_bank';

    protected $fillable = [
        'content_type','vacancy_id','written_exam_id','skill_test_id','title',
        'content','fingerprint','metadata','usage_count','review_status',
        'retired_at','created_by'
    ];

    protected $casts = [
        'metadata'=>'array',
        'retired_at'=>'datetime',
    ];
}
