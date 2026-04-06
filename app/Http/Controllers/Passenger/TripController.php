<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\DriverTrip;
use App\Models\PassengerTrip;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function store(Request $request)
    {
        $trip = PassengerTrip::create([
            'passenger_id' => $request->user()->id,
            'from_address' => $request->from_address,
            'to_address' => $request->to_address,
            'date' => $request->date,
            'time' => $request->time,
            'seats' => $request->seats,
            'amount' => $request->amount,
            'postman' => $request->postman ?? false,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'trip' => $trip,
        ], 201);
    }

    public function myTrips(Request $request)
    {
        $trips = PassengerTrip::where('passenger_id', $request->user()->id)
            ->where('status', '!=', 'completed')
            ->with('bookings.driver.cars')
            ->latest()
            ->paginate(10);

        return response()->json($trips);
    }

    public function history(Request $request)
    {
        $trips = PassengerTrip::where('passenger_id', $request->user()->id)
            ->where('status', 'completed')
            ->with('bookings.driver.cars')
            ->latest()
            ->paginate(10);

        return response()->json($trips);
    }

    public function activeDriverTrips(Request $request)
    {
        $query = DriverTrip::where('status', 'active')->with('driver.cars');

        if ($request->from_region) {
            $query->where('from_address', 'like', $request->from_region . '%');
        }
        if ($request->from_district) {
            $query->where('from_address', 'like', '%' . $request->from_district);
        }
        if ($request->to_region) {
            $query->where('to_address', 'like', $request->to_region . '%');
        }
        if ($request->to_district) {
            $query->where('to_address', 'like', '%' . $request->to_district);
        }
        if ($request->date) {
            $query->where('date', $request->date);
        }

        $trips = $query->latest()->paginate(10);

        return response()->json($trips);
    }

    public function complete(Request $request, $id)
    {
        $trip = PassengerTrip::where('id', $id)
            ->where('passenger_id', $request->user()->id)
            ->firstOrFail();

        $trip->update(['status' => 'completed']);

        // Complete all bookings for this trip
        $trip->bookings()->where('status', '!=', 'completed')->update(['status' => 'completed']);

        return response()->json([
            'trip' => $trip,
        ]);
    }
}
