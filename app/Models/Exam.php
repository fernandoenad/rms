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
        'vacancy_id',
        'title',
        'enrollment_key',
        'start_date',
        'end_date',
        'duration',
        'shuffle_items',
        'status',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function writtenExams(): HasMany
    {
        return $this->hasMany(WrittenExam::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function getStatus(): string
    {
        return $this->status === 1 ? 'Published' : 'Draft';
    }
}
