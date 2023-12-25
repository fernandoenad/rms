<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory;

    protected $fillable =[
        'application_code',
        'applicant_email',
        'applicant_fullname',
        'position_applied',
        'pertinent_doc'
    ];

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }
}
