<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasFactory;

    protected $fillable =[
        'type',
        'template',
        'status',
    ];

    public function vacancy(): HasMany
    {
        return $this->hasMany(Vacancy::class);
    }

    public function assessment(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

}
