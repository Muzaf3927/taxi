<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassengerBooking extends Model
{
    protected $fillable = [
        'passenger_id',
        'passenger_trip_id',
        'seats',
        'status',
        'offered_price',
        'comment',
    ];

    public function passenger()
    {
        return $this->belongsTo(Passenger::class);
    }

    public function trip()
    {
        return $this->belongsTo(DriverTrip::class, 'passenger_trip_id');
    }
}
