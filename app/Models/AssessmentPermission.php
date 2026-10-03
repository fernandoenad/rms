<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentPermission extends Model
{
    protected $fillable = ['user_id','capability','granted_by'];
}
