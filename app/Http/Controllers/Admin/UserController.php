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
}
