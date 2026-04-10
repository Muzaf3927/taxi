<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\DriverBooking;
use App\Models\DriverTrip;
use App\Models\PassengerBooking;
use App\Models\Passenger;
use App\Models\PassengerTrip;
use App\Models\Setting;
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

            // Notify via Telegram
            $passenger = Passenger::find($trip->passenger_id);

            if ($passenger && $passenger->telegram_id) {
                // Пассажиру — данные водителя
                TelegramNotificationService::notifyPassenger(
                    $passenger->telegram_id,
                    $driver->name,
                    $driver->phone,
                    $trip->from_address,
                    $trip->to_address
                );
            }

            // Водителю — данные пассажира (всегда, если есть telegram)
            if ($passenger && $driver->telegram_id) {
                TelegramNotificationService::notifyDriverCallPassenger(
                    $driver->telegram_id,
                    $passenger->name,
                    $passenger->phone,
                    $trip->from_address,
                    $trip->to_address
                );
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

            // Уведомить пассажира что бронь принята
            $passenger = \App\Models\Passenger::find($booking->passenger_id);
            $driver = $request->user();
            if ($passenger && $passenger->telegram_id) {
                TelegramNotificationService::notifyPassengerAccepted(
                    $passenger->telegram_id,
                    $driver->name,
                    $driver->phone,
                    $booking->seats,
                    $trip->from_address,
                    $trip->to_address
                );
            }

            // Водителю — номер пассажира
            if ($passenger && $driver->telegram_id) {
                TelegramNotificationService::notifyDriverCallPassenger(
                    $driver->telegram_id,
                    $passenger->name,
                    $passenger->phone,
                    $trip->from_address,
                    $trip->to_address
                );
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
        $trip = DriverTrip::find($booking->passenger_trip_id);

        // Уведомить пассажира что бронь отклонена
        $passenger = \App\Models\Passenger::find($booking->passenger_id);
        if ($passenger && $passenger->telegram_id && $trip) {
            TelegramNotificationService::notifyPassengerRejected(
                $passenger->telegram_id,
                $trip->from_address,
                $trip->to_address
            );
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

            // Уведомить пассажира что поездка завершена
            $passenger = Passenger::find($trip->passenger_id);
            if ($passenger && $passenger->telegram_id) {
                TelegramNotificationService::notifyTripCompleted(
                    $passenger->telegram_id,
                    env('TELEGRAM_PASSENGER_BOT_TOKEN'),
                    $trip->from_address,
                    $trip->to_address
                );
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
