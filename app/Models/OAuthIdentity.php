<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthIdentity extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['email_verified' => 'boolean'];
}
