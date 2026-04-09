<?php

namespace App\Http\Controllers\Passenger;

use App\Events\BookingCreated;
use App\Events\BookingStatusChanged;
use App\Events\TripStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverTrip;
use App\Models\PassengerBooking;
use App\Services\FcmService;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $trip = DriverTrip::lockForUpdate()->findOrFail($request->driver_trip_id);

            $exists = PassengerBooking::where('passenger_id', $request->user()->id)
                ->where('passenger_trip_id', $trip->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Siz allaqachon bu sayohatni band qilgansiz',
                ], 409);
            }

            $seats = $request->seats;

            if (!$request->offered_price) {
                // Прямое бронирование — проверяем и минусуем места
                if ($trip->available_seats < $seats) {
                    return response()->json([
                        'message' => "Bo'sh joy yetarli emas. Faqat {$trip->available_seats} ta joy qoldi.",
                    ], 409);
                }

                $booking = PassengerBooking::create([
                    'passenger_id' => $request->user()->id,
                    'passenger_trip_id' => $trip->id,
                    'seats' => $seats,
                    'status' => 'in_progress',
                    'offered_price' => $trip->amount,
                    'comment' => $request->comment,
                ]);

                $trip->decrement('available_seats', $seats);

                // Если места закончились — удаляем все requested и ставим трип in_progress
                if ($trip->fresh()->available_seats <= 0) {
                    PassengerBooking::where('passenger_trip_id', $trip->id)
                        ->where('status', 'requested')
                        ->delete();
                    $trip->update(['status' => 'in_progress']);
                }
            } else {
                // Предложение цены — места не трогаем
                $booking = PassengerBooking::create([
                    'passenger_id' => $request->user()->id,
                    'passenger_trip_id' => $trip->id,
                    'seats' => $seats,
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
                        $seats,
                        $trip->amount,
                        $trip->from_address,
                        $trip->to_address
                    );
                } else {
                    TelegramNotificationService::notifyDriverOffered(
                        $driver->telegram_id,
                        $passenger->name,
                        $passenger->phone,
                        $seats,
                        $request->offered_price,
                        $trip->from_address,
                        $trip->to_address
                    );
                }
            }

            // Real-time notification to driver
            if ($driver) {
                $booking->load('passenger');
                broadcast(new BookingCreated($booking->toArray(), 'driver', $driver->id));
                $msg = $request->offered_price
                    ? "Yangi narx taklifi: {$request->offered_price} so'm"
                    : "Yangi band: {$seats} ta joy";
                FcmService::sendToUser($driver, 'Yangi band!', $msg);
            }

            return response()->json([
                'booking' => $booking,
            ], 201);
        });
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

        $trip = DriverTrip::find($booking->passenger_trip_id);

        // Если бронь была принята (in_progress) — возвращаем места
        if ($booking->status === 'in_progress' && $trip) {
            $trip->increment('available_seats', $booking->seats);
            if ($trip->status === 'in_progress') {
                $trip->update(['status' => 'active']);
            }
        }

        // Notify driver
        if ($trip) {
            $driver = Driver::find($trip->driver_id);
            if ($driver) {
                broadcast(new BookingStatusChanged($booking->id, 'cancelled', $booking->toArray(), 'driver', $driver->id));
                broadcast(new TripStatusChanged($trip->id, 'driver_trip', $trip->fresh()->status, 'driver', $driver->id));
                FcmService::sendToUser($driver, 'Band bekor qilindi', "Yo'lovchi bandni bekor qildi: {$trip->from_address} → {$trip->to_address}");

                if ($driver->telegram_id) {
                    $telegram = new \App\Services\TelegramService(env('TELEGRAM_DRIVER_BOT_TOKEN'));
                    $telegram->sendMessage((int) $driver->telegram_id,
                        "⚠️ <b>Yo'lovchi bandni bekor qildi</b>\n\n" .
                        "📍 {$trip->from_address} → {$trip->to_address}\n\n" .
                        "Boshqa yo'lovchi qidirilmoqda..."
                    );
                }
            }
        }

        $booking->delete();

        return response()->json([
            'message' => 'Band bekor qilindi',
        ]);
    }
}
