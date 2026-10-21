<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantIntegrityFlagAction extends Model
{
    public $timestamps = false;
    protected $fillable = ['flag_id','actor_id','action','from_status','to_status','reason','details','created_at'];
    protected $casts = ['details'=>'array','created_at'=>'datetime'];
    public function flag(): BelongsTo { return $this->belongsTo(ApplicantIntegrityFlag::class, 'flag_id'); }
}
