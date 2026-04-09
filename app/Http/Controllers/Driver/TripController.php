<?php

namespace App\Http\Controllers\Driver;

use App\Events\DriverTripCreated;
use App\Events\TripStatusChanged;
use App\Events\BookingStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\DriverTrip;
use App\Models\PassengerTrip;
use App\Models\Setting;
use App\Services\FcmService;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function store(Request $request)
    {
        $driver = $request->user();

        if ($driver->balance < 10000) {
            $deficit = 10000 - $driver->balance;
            return response()->json([
                'message' => "Balansingiz yetarli emas. Sayohat yaratish uchun hisobingizni {$deficit} so'mga to'ldiring.",
                'deficit' => $deficit,
            ], 403);
        }

        $trip = DriverTrip::create([
            'driver_id' => $driver->id,
            'from_address' => $request->from_address,
            'to_address' => $request->to_address,
            'date' => $request->date,
            'time' => $request->time,
            'seats' => $request->seats,
            'available_seats' => $request->seats,
            'amount' => $request->amount,
            'postman' => $request->postman ?? false,
            'comment' => $request->comment,
        ]);

        $trip->load('driver.cars');
        broadcast(new DriverTripCreated($trip->toArray()));

        return response()->json([
            'trip' => $trip,
        ], 201);
    }

    public function myTrips(Request $request)
    {
        $trips = DriverTrip::where('driver_id', $request->user()->id)
            ->where('status', '!=', 'completed')
            ->with('bookings.passenger')
            ->latest()
            ->paginate(10);

        return response()->json($trips);
    }

    public function history(Request $request)
    {
        $trips = DriverTrip::where('driver_id', $request->user()->id)
            ->where('status', 'completed')
            ->with('bookings.passenger')
            ->latest()
            ->paginate(10);

        return response()->json($trips);
    }

    public function activePassengerTrips(Request $request)
    {
        $query = PassengerTrip::where('status', 'active')->with('passenger');

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
        $trip = DriverTrip::where('id', $id)
            ->where('driver_id', $request->user()->id)
            ->firstOrFail();

        $trip->update(['status' => 'completed']);

        // Complete all bookings for this trip
        $bookings = $trip->bookings()->where('status', '!=', 'completed')->get();
        $trip->bookings()->where('status', '!=', 'completed')->update(['status' => 'completed']);

        // Списание комиссии с водителя за каждое бронирование
        $driver = $request->user();
        $percentage = (float) Setting::where('name', 'commission_percentage')->value('value');

        if ($percentage > 0) {
            $totalCommission = 0;

            foreach ($bookings as $booking) {
                $bookingTotal = $booking->offered_price * $booking->seats;
                $commissionAmount = $bookingTotal * $percentage / 100;

                Commission::create([
                    'driver_id' => $driver->id,
                    'driver_trip_id' => $trip->id,
                    'percentage' => $percentage,
                    'type' => 'driver_trip',
                    'total_amount' => $commissionAmount,
                ]);

                $totalCommission += $commissionAmount;
            }

            if ($totalCommission > 0) {
                $driver->decrement('balance', $totalCommission);
            }
        }

        // Notify all passengers who had bookings on this trip
        foreach ($bookings as $booking) {
            $passenger = $booking->passenger;
            if ($passenger) {
                broadcast(new TripStatusChanged($trip->id, 'driver_trip', 'completed', 'passenger', $passenger->id));
                FcmService::sendToUser($passenger, 'Sayohat yakunlandi', "{$trip->from_address} → {$trip->to_address}");
            }
        }

        return response()->json([
            'trip' => $trip,
        ]);
    }
}
