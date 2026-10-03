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
        'voided_at', 'voided_by', 'void_reason', 'retake_exam_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
        'scored_at' => 'datetime',
        'auto_submitted' => 'boolean',
        'voided_at' => 'datetime',
    ];

    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function answers(): HasMany { return $this->hasMany(ExamAttemptAnswer::class); }
    public function itemOrders(): HasMany { return $this->hasMany(ExamAttemptItemOrder::class); }
    public function events(): HasMany { return $this->hasMany(AssessmentAttemptEvent::class); }
    public function timeExtensions(): HasMany { return $this->hasMany(AssessmentTimeExtension::class); }
    public function scoreChanges(): HasMany { return $this->hasMany(AssessmentScoreChange::class); }
}
