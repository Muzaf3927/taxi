<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Driver extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'telegram_id',
        'password',
        'phone',
        'balance',
        'is_blocked',
        'rating',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'is_blocked' => 'boolean',
            'rating' => 'decimal:2',
        ];
    }

    public function trips()
    {
        return $this->hasMany(DriverTrip::class);
    }

    public function bookings()
    {
        return $this->hasMany(DriverBooking::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function cars()
    {
        return $this->hasMany(Car::class);
    }
}
