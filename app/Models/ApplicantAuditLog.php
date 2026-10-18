<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantAuditLog extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];
}
