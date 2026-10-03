<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'vacancy_id', 'assessment_group_id', 'title', 'code', 'set_code', 'enrollment_key',
        'start_date', 'end_date', 'duration', 'access_mode',
        'shuffle_items', 'shuffle_options', 'status',
        'ai_context', 'ai_generation_focus',
        'ai_use_qualifications', 'ai_use_job_description',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'shuffle_items' => 'boolean',
        'shuffle_options' => 'boolean',
        'ai_use_qualifications' => 'boolean',
        'ai_use_job_description' => 'boolean',
    ];

    public function vacancy(): BelongsTo { return $this->belongsTo(Vacancy::class); }
    public function assessmentGroup(): BelongsTo { return $this->belongsTo(AssessmentGroup::class); }
    public function writtenExams(): HasMany { return $this->hasMany(WrittenExam::class); }
    public function attempts(): HasMany { return $this->hasMany(ExamAttempt::class); }

    public function getStatus(): string
    {
        if ($this->status !== 1) return 'Draft';
        if ($this->start_date && now()->lt($this->start_date)) return 'Scheduled';
        if ($this->end_date && now()->gt($this->end_date)) return 'Closed';
        return 'Open';
    }
}
