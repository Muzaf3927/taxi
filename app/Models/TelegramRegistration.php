<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramRegistration extends Model
{
    protected $fillable = [
        'chat_id',
        'phone',
        'name',
        'step',
        'role',
        'otp',
        'otp_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
        ];
    }
}
