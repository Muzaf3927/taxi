<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Passenger;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    public function updateDriverBalance(Request $request)
    {
        $driver = Driver::where('phone', $request->phone)->firstOrFail();
        $driver->update([
            'balance' => $driver->balance + $request->amount,
        ]);

        return response()->json([
            'driver' => $driver,
        ]);
    }

    public function updatePassengerBalance(Request $request)
    {
        $passenger = Passenger::where('phone', $request->phone)->firstOrFail();
        $passenger->update([
            'balance' => $passenger->balance + $request->amount,
        ]);

        return response()->json([
            'passenger' => $passenger,
        ]);
    }
}
