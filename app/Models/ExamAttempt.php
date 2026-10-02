<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id', 'application_id', 'started_at', 'expires_at', 'ended_at',
        'status', 'correct_answers', 'total_items', 'percentage', 'scored_at',
        'question_order', 'auto_submitted', 'auto_submit_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
        'scored_at' => 'datetime',
        'auto_submitted' => 'boolean',
    ];

    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function answers(): HasMany { return $this->hasMany(ExamAttemptAnswer::class); }
    public function itemOrders(): HasMany { return $this->hasMany(ExamAttemptItemOrder::class); }
    public function events(): HasMany { return $this->hasMany(AssessmentAttemptEvent::class); }
}
