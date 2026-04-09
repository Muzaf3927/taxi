<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Passenger extends Model
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
        'fcm_token',
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
        return $this->hasMany(PassengerTrip::class);
    }

    public function bookings()
    {
        return $this->hasMany(PassengerBooking::class);
    }
}
