<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverTrip;
use App\Models\Passenger;
use App\Models\PassengerTrip;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function createPassengerTrip(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'name' => 'required|string',
            'from_address' => 'required|string',
            'to_address' => 'required|string',
            'date' => 'required|date',
            'time' => 'required|string',
            'seats' => 'required|integer|min:1|max:4',
            'amount' => 'required|numeric|min:0',
        ]);

        // Найти или создать пассажира по номеру телефона
        $passenger = Passenger::firstOrCreate(
            ['phone' => $request->phone],
            ['name' => $request->name, 'balance' => 0, 'rating' => 5.00]
        );

        // Обновить имя если уже существует
        if ($passenger->name !== $request->name) {
            $passenger->update(['name' => $request->name]);
        }

        $trip = PassengerTrip::create([
            'passenger_id' => $passenger->id,
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
            'trip' => $trip->load('passenger'),
        ], 201);
    }

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
