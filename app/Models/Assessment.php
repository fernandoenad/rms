<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable =[
        'application_id',
        'template_id',
        'assessment',
        'score',
        'status',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function get_status()
    {
        $label = null;

        switch($this->status){
            case 0: $label = 'New'; break;
            case 1: $label = 'SRC Pending'; break;
            case 2: $label = 'SRC Completed/DRC Pending'; break;
            case 3: $label = 'DRC Completed'; break;
            case 4: $label = 'Disqualified'; break;
        }

        return $label;
    }
}
