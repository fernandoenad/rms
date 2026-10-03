<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillTestAiEvaluation extends Model
{
    protected $fillable = [
        'skill_test_attempt_id','status','provider','model','prompt_version','criterion_scores',
        'proposed_total','flags','raw_response','error_message','started_at','completed_at'
    ];

    protected $casts = [
        'criterion_scores'=>'array','flags'=>'array',
        'started_at'=>'datetime','completed_at'=>'datetime'
    ];
}
