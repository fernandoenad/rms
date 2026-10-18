<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicantProfile extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['profile_completed_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function applicationLinks(): HasMany { return $this->hasMany(ApplicantApplicationLink::class); }
    public function claims(): HasMany { return $this->hasMany(ApplicationClaimRequest::class); }
}
