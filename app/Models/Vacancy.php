<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


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
    ];

    public function application(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function getOffice(){
        if($this->office_level == 1){
            $office_level = "Field";
        } else {
            $office_level = "SDO";
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

}
