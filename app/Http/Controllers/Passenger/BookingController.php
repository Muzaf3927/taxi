<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverTrip;
use App\Models\PassengerBooking;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $trip = DriverTrip::findOrFail($request->driver_trip_id);

        $exists = PassengerBooking::where('passenger_id', $request->user()->id)
            ->where('passenger_trip_id', $trip->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Siz allaqachon bu sayohatni band qilgansiz',
            ], 409);
        }

        if (!$request->offered_price) {
            $booking = PassengerBooking::create([
                'passenger_id' => $request->user()->id,
                'passenger_trip_id' => $trip->id,
                'seats' => $request->seats,
                'status' => 'in_progress',
                'offered_price' => $trip->amount,
                'comment' => $request->comment,
            ]);
        } else {
            $booking = PassengerBooking::create([
                'passenger_id' => $request->user()->id,
                'passenger_trip_id' => $trip->id,
                'seats' => $request->seats,
                'status' => 'requested',
                'offered_price' => $request->offered_price,
                'comment' => $request->comment,
            ]);
        }

        // Notify driver via Telegram
        $driver = Driver::find($trip->driver_id);
        $passenger = $request->user();
        if ($driver && $driver->telegram_id) {
            if (!$request->offered_price) {
                TelegramNotificationService::notifyDriverBooked(
                    $driver->telegram_id,
                    $passenger->name,
                    $passenger->phone,
                    $request->seats,
                    $trip->amount,
                    $trip->from_address,
                    $trip->to_address
                );
            } else {
                TelegramNotificationService::notifyDriverOffered(
                    $driver->telegram_id,
                    $passenger->name,
                    $passenger->phone,
                    $request->seats,
                    $request->offered_price,
                    $trip->from_address,
                    $trip->to_address
                );
            }
        }

        return response()->json([
            'booking' => $booking,
        ], 201);
    }

    public function myBookings(Request $request)
    {
        $bookings = PassengerBooking::where('passenger_id', $request->user()->id)
            ->where('status', '!=', 'completed')
            ->with(['trip.driver.cars'])
            ->latest()
            ->paginate(10);

        return response()->json($bookings);
    }

    public function cancel(Request $request, $id)
    {
        $booking = PassengerBooking::where('id', $id)
            ->where('passenger_id', $request->user()->id)
            ->firstOrFail();

        // Notify driver
        $trip = DriverTrip::find($booking->passenger_trip_id);
        if ($trip) {
            $driver = Driver::find($trip->driver_id);
            if ($driver && $driver->telegram_id) {
                $telegram = new \App\Services\TelegramService(env('TELEGRAM_DRIVER_BOT_TOKEN'));
                $telegram->sendMessage((int) $driver->telegram_id,
                    "⚠️ <b>Yo'lovchi bandni bekor qildi</b>\n\n" .
                    "📍 {$trip->from_address} → {$trip->to_address}\n\n" .
                    "Boshqa yo'lovchi qidirilmoqda..."
                );
            }
        }

        $booking->delete();

        return response()->json([
            'message' => 'Band bekor qilindi',
        ]);
    }
}
