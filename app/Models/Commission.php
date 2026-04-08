<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = [
        'driver_id',
        'driver_trip_id',
        'passenger_trip_id',
        'percentage',
        'type',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function driverTrip()
    {
        return $this->belongsTo(DriverTrip::class, 'driver_trip_id');
    }

    public function passengerTrip()
    {
        return $this->belongsTo(PassengerTrip::class, 'passenger_trip_id');
    }
}
