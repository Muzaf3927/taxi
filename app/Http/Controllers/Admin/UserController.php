<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Passenger;

class UserController extends Controller
{
    public function drivers()
    {
        $drivers = Driver::with('cars')->latest()->paginate(10);

        return response()->json($drivers);
    }

    public function passengers()
    {
        $passengers = Passenger::latest()->paginate(10);

        return response()->json($passengers);
    }

    public function toggleDriverBlock($id)
    {
        $driver = Driver::findOrFail($id);
        $driver->update(['is_blocked' => !$driver->is_blocked]);

        return response()->json(['driver' => $driver]);
    }

    public function togglePassengerBlock($id)
    {
        $passenger = Passenger::findOrFail($id);
        $passenger->update(['is_blocked' => !$passenger->is_blocked]);

        return response()->json(['passenger' => $passenger]);
    }
}
