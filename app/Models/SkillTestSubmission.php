<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillTestSubmission extends Model
{
    protected $fillable = [
        'skill_test_attempt_id','version','inline_response','file_path','original_filename',
        'mime_type','file_size','is_final','submitted_at'
    ];

    protected $casts = ['is_final'=>'boolean','submitted_at'=>'datetime'];
}
