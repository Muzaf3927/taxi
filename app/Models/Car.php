<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $fillable = [
        'driver_id',
        'number',
        'color',
        'model',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
