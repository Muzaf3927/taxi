<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassengerTrip extends Model
{
    protected $fillable = [
        'passenger_id',
        'from_address',
        'to_address',
        'postman',
        'date',
        'time',
        'amount',
        'seats',
        'status',
        'from_lat',
        'from_lng',
        'to_lat',
        'to_lng',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'from_lat' => 'decimal:7',
            'from_lng' => 'decimal:7',
            'to_lat' => 'decimal:7',
            'to_lng' => 'decimal:7',
        ];
    }

    public function passenger()
    {
        return $this->belongsTo(Passenger::class);
    }

    public function bookings()
    {
        return $this->hasMany(DriverBooking::class, 'driver_trip_id');
    }
}
