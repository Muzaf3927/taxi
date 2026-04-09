<?php

namespace App\Http\Controllers\Driver;

use App\Events\BookingCreated;
use App\Events\BookingStatusChanged;
use App\Events\TripStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\DriverBooking;
use App\Models\DriverTrip;
use App\Models\PassengerBooking;
use App\Models\Passenger;
use App\Models\PassengerTrip;
use App\Models\Setting;
use App\Services\FcmService;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $trip = PassengerTrip::lockForUpdate()->findOrFail($request->passenger_trip_id);

            // Проверяем что трип ещё свободен
            if ($trip->status !== 'active') {
                return response()->json([
                    'message' => 'Bu sayohat allaqachon band qilingan.',
                ], 409);
            }

            $exists = DriverBooking::where('driver_id', $request->user()->id)
                ->where('driver_trip_id', $trip->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Siz allaqachon bu sayohatni band qilgansiz',
                ], 409);
            }

            $driver = $request->user();
            $percentage = (float) Setting::where('name', 'commission_percentage')->value('value');
            $commissionAmount = $trip->amount * $percentage / 100;

            if ($driver->balance < $commissionAmount) {
                $deficit = $commissionAmount - $driver->balance;
                return response()->json([
                    'message' => "Balansingiz yetarli emas. Band qilish uchun hisobingizni {$deficit} so'mga to'ldiring.",
                    'deficit' => $deficit,
                ], 403);
            }

            $trip->update(['status' => 'in_progress']);

            $booking = DriverBooking::create([
                'driver_id' => $driver->id,
                'driver_trip_id' => $trip->id,
                'seats' => $trip->seats,
                'status' => 'in_progress',
                'offered_price' => $trip->amount,
                'comment' => $request->comment,
            ]);

            // Notify passenger via Telegram
            $passenger = Passenger::find($trip->passenger_id);
            if ($passenger && $passenger->telegram_id) {
                TelegramNotificationService::notifyPassenger(
                    $passenger->telegram_id,
                    $driver->name,
                    $driver->phone,
                    $trip->from_address,
                    $trip->to_address
                );
            }

            // Real-time notification to passenger
            if ($passenger) {
                $booking->load('driver.cars');
                broadcast(new BookingCreated($booking->toArray(), 'passenger', $passenger->id));
                broadcast(new TripStatusChanged($trip->id, 'passenger_trip', 'in_progress', 'passenger', $passenger->id));
                FcmService::sendToUser($passenger, 'Haydovchi topildi!', "{$driver->name} sizning sayohatingizni qabul qildi");
            }

            return response()->json([
                'booking' => $booking,
            ], 201);
        });
    }

    public function accept(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $booking = PassengerBooking::findOrFail($id);
            $trip = DriverTrip::lockForUpdate()->findOrFail($booking->passenger_trip_id);

            // Проверяем хватает ли мест
            if ($trip->available_seats < $booking->seats) {
                return response()->json([
                    'message' => "{$trip->available_seats} ta bo'sh joy qoldi, {$booking->seats} ta joy uchun qabul qilib bo'lmaydi.",
                ], 409);
            }

            $booking->update(['status' => 'in_progress']);
            $trip->decrement('available_seats', $booking->seats);

            // Если места закончились — удаляем все requested и трип → in_progress
            if ($trip->fresh()->available_seats <= 0) {
                PassengerBooking::where('passenger_trip_id', $trip->id)
                    ->where('status', 'requested')
                    ->delete();
                $trip->update(['status' => 'in_progress']);
            }

            // Notify passenger that booking was accepted
            broadcast(new BookingStatusChanged($booking->id, 'in_progress', $booking->toArray(), 'passenger', $booking->passenger_id));
            $passengerModel = Passenger::find($booking->passenger_id);
            if ($passengerModel) {
                FcmService::sendToUser($passengerModel, 'Band qabul qilindi!', "{$trip->from_address} → {$trip->to_address}");
            }

            // Авто-отмена дублей пассажира (тот же день, < 1 час разницы)
            $tripTime = Carbon::parse($trip->date->format('Y-m-d') . ' ' . $trip->time);
            $passengerOtherBookings = PassengerBooking::where('passenger_id', $booking->passenger_id)
                ->where('id', '!=', $booking->id)
                ->where('status', 'requested')
                ->get();

            foreach ($passengerOtherBookings as $otherBooking) {
                $otherTrip = DriverTrip::find($otherBooking->passenger_trip_id);
                if (!$otherTrip) continue;

                $otherTripTime = Carbon::parse($otherTrip->date->format('Y-m-d') . ' ' . $otherTrip->time);

                // Тот же день и разница < 1 час
                if ($tripTime->isSameDay($otherTripTime) && abs($tripTime->diffInMinutes($otherTripTime)) < 60) {
                    $otherBooking->delete();
                }
            }

            return response()->json([
                'booking' => $booking,
            ]);
        });
    }

    public function reject(Request $request, $id)
    {
        $booking = PassengerBooking::findOrFail($id);
        $passengerId = $booking->passenger_id;

        broadcast(new BookingStatusChanged($booking->id, 'rejected', $booking->toArray(), 'passenger', $passengerId));
        $passengerModel = Passenger::find($passengerId);
        if ($passengerModel) {
            FcmService::sendToUser($passengerModel, 'Band rad etildi', 'Haydovchi sizning bandingizni rad etdi');
        }

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
            // Complete all other bookings for this trip
            DriverBooking::where('driver_trip_id', $trip->id)
                ->where('status', '!=', 'completed')
                ->update(['status' => 'completed']);

            // Notify passenger
            $passenger = Passenger::find($trip->passenger_id);
            if ($passenger) {
                broadcast(new BookingStatusChanged($booking->id, 'completed', $booking->toArray(), 'passenger', $passenger->id));
                broadcast(new TripStatusChanged($trip->id, 'passenger_trip', 'completed', 'passenger', $passenger->id));
                FcmService::sendToUser($passenger, 'Sayohat yakunlandi', "{$trip->from_address} → {$trip->to_address}");
            }
        }

        // Списание комиссии с водителя
        $driver = $request->user();
        $percentage = (float) Setting::where('name', 'commission_percentage')->value('value');

        if ($percentage > 0) {
            $commissionAmount = $booking->offered_price * $percentage / 100;

            Commission::create([
                'driver_id' => $driver->id,
                'passenger_trip_id' => $trip?->id,
                'percentage' => $percentage,
                'type' => 'passenger_trip',
                'total_amount' => $commissionAmount,
            ]);

            $driver->decrement('balance', $commissionAmount);
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
            if ($passenger) {
                broadcast(new BookingStatusChanged($booking->id, 'cancelled', $booking->toArray(), 'passenger', $passenger->id));
                broadcast(new TripStatusChanged($trip->id, 'passenger_trip', 'active', 'passenger', $passenger->id));
                FcmService::sendToUser($passenger, 'Band bekor qilindi', "Haydovchi bandni bekor qildi: {$trip->from_address} → {$trip->to_address}");

                if ($passenger->telegram_id) {
                    $telegram = new \App\Services\TelegramService(env('TELEGRAM_PASSENGER_BOT_TOKEN'));
                    $telegram->sendMessage((int) $passenger->telegram_id,
                        "⚠️ <b>Haydovchi bandni bekor qildi</b>\n\n" .
                        "📍 {$trip->from_address} → {$trip->to_address}\n\n" .
                        "Boshqa haydovchi qidirilmoqda..."
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
