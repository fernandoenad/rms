<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;

    protected $connection = 'mysql_2';

    public function office()
    {
        return $this->belongsTo(Office::class);
    }
}
