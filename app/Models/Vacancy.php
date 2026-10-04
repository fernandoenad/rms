<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vacancy extends Model
{
    use HasFactory;

    protected $casts = [
        'posting_start_at' => 'datetime',
        'posting_end_at' => 'datetime',
    ];

    protected $fillable =[
        'cycle',
        'position_title',
        'salary_grade',
        'base_pay',
        'office_level',
        'qualifications',
        'vacancy',
        'status',
        'posting_start_at',
        'posting_end_at',
        'template_id',
        'level1_status',
        'level2_status',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function assessmentGroups(): HasMany
    {
        return $this->hasMany(AssessmentGroup::class);
    }

    public function getOffice(){
        if($this->office_level == 0){
            $office_level = "SDO";
        } else {
            $office_level = "Field";
        }

        return $office_level;
    }

    public function getStatus(){
        return $this->getPostingStatus();
    }

    public function getPostingStatus(): string
    {
        if ((int) $this->status !== 1) return 'Draft';

        $now = now();
        if ($this->posting_start_at && $now->lt($this->posting_start_at)) return 'Scheduled';
        if ($this->posting_end_at && $now->gte($this->posting_end_at)) return 'Closed';

        return 'Open for Applications';
    }

    public function isOpenForApplications(): bool
    {
        if ((int) $this->status !== 1) return false;

        $now = now();
        if ($this->posting_start_at && $now->lt($this->posting_start_at)) return false;
        if ($this->posting_end_at && $now->gte($this->posting_end_at)) return false;

        return true;
    }

    public function scopeOpenForApplications($query)
    {
        $now = now();

        return $query
            ->where('status', 1)
            ->where(function ($q) use ($now) {
                $q->whereNull('posting_start_at')->orWhere('posting_start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('posting_end_at')->orWhere('posting_end_at', '>', $now);
            });
    }

    public function getLevel1Status(){
        if($this->level1_status == 0){
            $status_name = "Closed";
        } else if($this->level1_status == 1){
            $status_name = "Open";
        } else if($this->level1_status == 2){
            $status_name = "Completed";
        } else {
            $status_name = "";
        }

        return $status_name;
    }

    public function getLevel2Status(){
        if($this->level2_status == 0){
            $status_name = "Closed";
        } else if($this->level2_status == 1){
            $status_name = "Open";
        } else if($this->level2_status == 2){
            $status_name = "Completed";
        } else if($this->level2_status == 3){
            $status_name = "Posted";
        } else {
            $status_name = "";
        }

        return $status_name;
    }

}
