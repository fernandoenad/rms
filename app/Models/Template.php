<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
    use HasFactory;

    protected $fillable =[
        'type',
        'description',
        'version',
        'parent_template_id',
        'template',
        'status',
        'archived_at',
    ];

    public function vacancy(): HasMany
    {
        return $this->hasMany(Vacancy::class);
    }

    public function assessment(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(ScoringTemplateCriterion::class)->orderBy('sort_order');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_template_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_template_id');
    }

    public function getTotalPointsAttribute(): float
    {
        if ($this->relationLoaded('criteria')) {
            return (float) $this->criteria->where('is_active', true)->sum('max_points');
        }

        return (float) $this->criteria()->where('is_active', true)->sum('max_points');
    }

    protected $casts = [
        'version' => 'integer',
        'status' => 'integer',
        'archived_at' => 'datetime',
    ];
}
