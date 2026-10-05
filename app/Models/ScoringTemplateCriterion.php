<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringTemplateCriterion extends Model
{
    protected $fillable = [
        'template_id',
        'key',
        'label',
        'max_points',
        'source_type',
        'instructions',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'max_points' => 'float',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
