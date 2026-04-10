<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverTrip;
use App\Models\PassengerTrip;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function driverTrips(Request $request)
    {
        $query = DriverTrip::with('driver.cars', 'bookings.passenger');

        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '!=', 'completed');
        }

        if ($request->driver_name) {
            $query->whereHas('driver', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->driver_name . '%');
            });
        }

        if ($request->from_address) {
            $query->where('from_address', 'like', '%' . $request->from_address . '%');
        }

        if ($request->to_address) {
            $query->where('to_address', 'like', '%' . $request->to_address . '%');
        }

        if ($request->date) {
            $query->where('date', $request->date);
        }

        $trips = $query->latest()->paginate(15);

        return response()->json($trips);
    }

    public function passengerTrips(Request $request)
    {
        $query = PassengerTrip::with('passenger', 'bookings.driver.cars');

        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '!=', 'completed');
        }

        if ($request->passenger_name) {
            $query->whereHas('passenger', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->passenger_name . '%');
            });
        }

        if ($request->from_address) {
            $query->where('from_address', 'like', '%' . $request->from_address . '%');
        }

        if ($request->to_address) {
            $query->where('to_address', 'like', '%' . $request->to_address . '%');
        }

        if ($request->date) {
            $query->where('date', $request->date);
        }

        $trips = $query->latest()->paginate(15);

        return response()->json($trips);
    }
}
