<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationClaimRequest extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['reviewed_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function ownershipLink(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ApplicantApplicationLink::class, 'application_id', 'application_id');
    }

    public function profile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ApplicantProfile::class, 'applicant_profile_id');
    }
}
