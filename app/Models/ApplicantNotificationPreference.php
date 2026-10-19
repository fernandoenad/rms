<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantNotificationPreference extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'email_enabled' => 'boolean',
        'email_application_updates' => 'boolean',
        'email_assessment_updates' => 'boolean',
    ];
}
