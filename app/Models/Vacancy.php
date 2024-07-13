<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vacancy extends Model
{
    use HasFactory;

    protected $fillable =[
        'cycle',
        'position_title',
        'salary_grade',
        'base_pay',
        'office_level',
        'qualifications',
        'vacancy',
        'status',
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

    public function getOffice(){
        if($this->office_level == 0){
            $office_level = "SDO";
        } else {
            $office_level = "Field";
        }

        return $office_level;
    }

    public function getStatus(){
        if($this->status == 1){
            $status_name = "Posted";
        } else {
            $status_name = "Draft";
        }

        return $status_name;
    }

    public function getLevel1Status(){
        if($this->status == 1){
            $status_name = "";
        } else {
            $status_name = "disabled";
        }

        return $status_name;
    }

    public function getLevel2Status(){
        if($this->status == 1){
            $status_name = "";
        } else {
            $status_name = "disabled";
        }

        return $status_name;
    }

}
