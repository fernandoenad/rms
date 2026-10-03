<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillTestRubricCriterion extends Model
{
    protected $fillable = [
        'skill_test_id','criterion','description','max_points','sort_order',
        'criterion_version','supersedes_criterion_id','review_status',
        'reviewed_by','reviewed_at','review_notes'
    ];

    protected $casts = ['reviewed_at'=>'datetime'];
}
