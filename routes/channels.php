<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('driver.{driverId}', function ($user, $driverId) {
    return (int) $user->id === (int) $driverId;
});

Broadcast::channel('passenger.{passengerId}', function ($user, $passengerId) {
    return (int) $user->id === (int) $passengerId;
});
