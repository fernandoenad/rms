<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantApplicationLink extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['verified_at' => 'datetime'];

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function profile(): BelongsTo { return $this->belongsTo(ApplicantProfile::class, 'applicant_profile_id'); }
}
