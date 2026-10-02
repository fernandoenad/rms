<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillTestRubricCriterion extends Model
{
    protected $fillable = ['skill_test_id','criterion','description','max_points','sort_order'];
}
