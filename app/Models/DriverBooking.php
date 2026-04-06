<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverBooking extends Model
{
    protected $fillable = [
        'driver_id',
        'driver_trip_id',
        'seats',
        'status',
        'offered_price',
        'comment',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function trip()
    {
        return $this->belongsTo(PassengerTrip::class, 'driver_trip_id');
    }
}
