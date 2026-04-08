<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Passenger;
use App\Models\TelegramRegistration;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function passenger(Request $request)
    {
        $telegram = new TelegramService(env('TELEGRAM_PASSENGER_BOT_TOKEN'));
        return $this->handle($request, $telegram, 'passenger');
    }

    public function driver(Request $request)
    {
        $telegram = new TelegramService(env('TELEGRAM_DRIVER_BOT_TOKEN'));
        return $this->handle($request, $telegram, 'driver');
    }

    protected function handle(Request $request, TelegramService $telegram, string $role)
    {
        $update = $request->all();
        $message = $update['message'] ?? null;

        if (!$message) return response('ok');

        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $contact = $message['contact'] ?? null;
        $firstName = $message['from']['first_name'] ?? 'User';

        $reg = TelegramRegistration::firstOrCreate(
            ['chat_id' => $chatId . '_' . $role],
            ['step' => 'start', 'role' => $role]
        );

        // /start или повторный запрос
        if ($text === '/start' || $reg->step === 'start') {
            $reg->update(['step' => 'phone', 'phone' => null, 'name' => null, 'role' => $role]);
            $roleName = $role === 'passenger' ? "Yo'lovchi" : "Haydovchi";
            $telegram->requestContact($chatId, "🚕 <b>Taksi - $roleName</b>\n\n📱 Telefon raqamingizni yuboring:");
            return response('ok');
        }

        // Получили номер телефона
        if ($reg->step === 'phone') {
            if ($contact) {
                $phone = ltrim($contact['phone_number'], '+');
                if (str_starts_with($phone, '998')) {
                    $phone = substr($phone, 3);
                }

                // Создаём/обновляем пользователя
                $data = [
                    'name' => $firstName,
                    'phone' => $phone,
                    'telegram_id' => (string) $chatId,
                ];

                if ($role === 'passenger') {
                    $user = Passenger::where('phone', $phone)->orWhere('telegram_id', (string) $chatId)->first();
                    if ($user) {
                        $user->update($data);
                    } else {
                        Passenger::create($data);
                    }
                } else {
                    $user = Driver::where('phone', $phone)->orWhere('telegram_id', (string) $chatId)->first();
                    if ($user) {
                        $user->update($data);
                    } else {
                        Driver::create($data);
                    }
                }

                // Генерируем уникальный OTP
                $otp = $this->generateUniqueOtp($role);

                $reg->update([
                    'phone' => $phone,
                    'name' => $firstName,
                    'step' => 'done',
                    'otp' => $otp,
                    'otp_expires_at' => now()->addMinutes(5),
                ]);

                $telegram->removeKeyboard($chatId,
                    "✅ <b>Kod yaratildi!</b>\n\n" .
                    "🔑 Sizning kodingiz: <b>$otp</b>\n\n" .
                    "Ilovaga qaytib ushbu kodni kiriting.\n" .
                    "⏱ Kod 5 daqiqa amal qiladi.\n\n" .
                    "Yangi kod olish uchun istalgan xabar yuboring."
                );
            } else {
                $telegram->requestContact($chatId, "Iltimos, quyidagi tugmani bosib raqamingizni yuboring:");
            }
            return response('ok');
        }

        // Повторный запрос OTP (после done)
        if ($reg->step === 'done' && $reg->phone) {
            $otp = $this->generateUniqueOtp($role);

            $reg->update([
                'otp' => $otp,
                'otp_expires_at' => now()->addMinutes(5),
            ]);

            $telegram->sendMessage($chatId,
                "🔑 Yangi kodingiz: <b>$otp</b>\n\n" .
                "Ilovaga qaytib ushbu kodni kiriting.\n" .
                "⏱ Kod 5 daqiqa amal qiladi."
            );
            return response('ok');
        }

        $telegram->sendMessage($chatId, "Boshlash uchun /start bosing.");
        return response('ok');
    }

    protected function generateUniqueOtp(string $role): string
    {
        do {
            $otp = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (
            TelegramRegistration::where('role', $role)
                ->where('otp', $otp)
                ->where('otp_expires_at', '>', now())
                ->exists()
        );

        return $otp;
    }
}
