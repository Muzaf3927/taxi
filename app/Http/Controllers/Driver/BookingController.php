<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverBooking;
use App\Models\DriverTrip;
use App\Models\PassengerBooking;
use App\Models\Passenger;
use App\Models\PassengerTrip;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $trip = PassengerTrip::findOrFail($request->passenger_trip_id);

        $exists = DriverBooking::where('driver_id', $request->user()->id)
            ->where('driver_trip_id', $trip->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Siz allaqachon bu sayohatni band qilgansiz',
            ], 409);
        }

        $trip->update(['status' => 'in_progress']);

        $booking = DriverBooking::create([
            'driver_id' => $request->user()->id,
            'driver_trip_id' => $trip->id,
            'seats' => $trip->seats,
            'status' => 'in_progress',
            'offered_price' => $trip->amount,
            'comment' => $request->comment,
        ]);

        // Notify passenger via Telegram
        $passenger = Passenger::find($trip->passenger_id);
        $driver = $request->user();
        if ($passenger && $passenger->telegram_id) {
            TelegramNotificationService::notifyPassenger(
                $passenger->telegram_id,
                $driver->name,
                $driver->phone,
                $trip->from_address,
                $trip->to_address
            );
        }

        return response()->json([
            'booking' => $booking,
        ], 201);
    }

    public function accept(Request $request, $id)
    {
        $booking = PassengerBooking::findOrFail($id);

        $booking->update(['status' => 'in_progress']);

        return response()->json([
            'booking' => $booking,
        ]);
    }

    public function reject(Request $request, $id)
    {
        $booking = PassengerBooking::findOrFail($id);

        $booking->delete();

        return response()->json([
            'message' => 'Band rad etildi',
        ]);
    }

    public function myBookings(Request $request)
    {
        $bookings = DriverBooking::where('driver_id', $request->user()->id)
            ->where('status', '!=', 'completed')
            ->with(['trip.passenger'])
            ->latest()
            ->paginate(10);

        return response()->json($bookings);
    }

    public function complete(Request $request, $id)
    {
        $booking = DriverBooking::where('id', $id)
            ->where('driver_id', $request->user()->id)
            ->firstOrFail();

        $booking->update(['status' => 'completed']);

        $trip = PassengerTrip::find($booking->driver_trip_id);
        if ($trip) {
            $trip->update(['status' => 'completed']);
        }

        return response()->json([
            'booking' => $booking,
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $booking = DriverBooking::where('id', $id)
            ->where('driver_id', $request->user()->id)
            ->firstOrFail();

        $trip = PassengerTrip::find($booking->driver_trip_id);
        if ($trip && $trip->status === 'in_progress') {
            $trip->update(['status' => 'active']);
        }

        // Notify passenger
        if ($trip) {
            $passenger = Passenger::find($trip->passenger_id);
            if ($passenger && $passenger->telegram_id) {
                $telegram = new \App\Services\TelegramService(env('TELEGRAM_PASSENGER_BOT_TOKEN'));
                $telegram->sendMessage((int) $passenger->telegram_id,
                    "⚠️ <b>Haydovchi bandni bekor qildi</b>\n\n" .
                    "📍 {$trip->from_address} → {$trip->to_address}\n\n" .
                    "Boshqa haydovchi qidirilmoqda..."
                );
            }
        }

        $booking->delete();

        return response()->json([
            'message' => 'Band bekor qilindi',
        ]);
    }
}
