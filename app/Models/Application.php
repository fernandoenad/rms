<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    use HasFactory;

    protected $fillable =[
        'vacancy_id',
        'application_code',
        'first_name',
        'middle_name',
        'last_name',
        'sitio',
        'barangay',
        'municipality',
        'zip',
        'age',
        'gender',
        'civil_status',
        'religion',
        'disability',
        'ethnic_group',
        'email',
        'phone',
        'station_id',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(Assessment::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function getFullname()
    {
        return $this->last_name . ', ' . $this->first_name . ' ' . substr($this->middle_name, 0, 1);
    }

    public function getFullname2()
    {
        return $this->last_name . ', ' . $this->first_name . ' ' . $this->middle_name;
    }

    public function getAddress()
    {
        return $this->sitio . ', ' . $this->barangay . ', ' . $this->municipality . ' (' . $this->zip . ')';
    }

}
