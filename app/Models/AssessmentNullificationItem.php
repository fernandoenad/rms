<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentNullificationItem extends Model
{
    protected $fillable = [
        'batch_id', 'application_id', 'criterion_key',
        'previously_present', 'previous_value_json',
    ];

    protected $casts = ['previously_present' => 'boolean'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AssessmentNullificationBatch::class, 'batch_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
