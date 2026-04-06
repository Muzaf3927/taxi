<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Passenger;
use App\Models\TelegramRegistration;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

        $reg = TelegramRegistration::firstOrCreate(
            ['chat_id' => $chatId . '_' . $role],
            ['step' => 'start', 'role' => $role]
        );

        // /start
        if ($text === '/start') {
            $reg->update(['step' => 'phone', 'phone' => null, 'name' => null, 'role' => $role]);
            $roleName = $role === 'passenger' ? "Yo'lovchi" : "Haydovchi";
            $telegram->requestContact($chatId, "🚕 <b>Taksi - $roleName ro'yxatdan o'tish</b>\n\n📱 Telefon raqamingizni yuboring:");
            return response('ok');
        }

        // Phone
        if ($reg->step === 'phone') {
            if ($contact) {
                $phone = ltrim($contact['phone_number'], '+');
                if (str_starts_with($phone, '998')) {
                    $phone = substr($phone, 3);
                }
                $reg->update(['phone' => $phone, 'step' => 'name']);
                $telegram->removeKeyboard($chatId, "✅ Raqam qabul qilindi: <b>$phone</b>\n\nIsmingizni kiriting:");
            } else {
                $telegram->requestContact($chatId, "Iltimos, quyidagi tugmani bosib raqamingizni yuboring:");
            }
            return response('ok');
        }

        // Name
        if ($reg->step === 'name') {
            if (mb_strlen($text) < 2) {
                $telegram->sendMessage($chatId, "Iltimos, to'g'ri ism kiriting.");
                return response('ok');
            }
            $reg->update(['name' => $text, 'step' => 'password']);
            $telegram->sendMessage($chatId, "🔐 Parol o'rnating (kamida 6 ta belgi):");
            return response('ok');
        }

        // Password
        if ($reg->step === 'password') {
            if (mb_strlen($text) < 6) {
                $telegram->sendMessage($chatId, "Parol kamida 6 ta belgidan iborat bo'lishi kerak. Qaytadan kiriting:");
                return response('ok');
            }

            $data = [
                'name' => $reg->name,
                'password' => Hash::make($text),
                'phone' => $reg->phone,
                'telegram_id' => $chatId,
            ];

            if ($role === 'passenger') {
                $user = Passenger::where('phone', $reg->phone)->orWhere('telegram_id', $chatId)->first();
                if ($user) {
                    $user->update($data);
                } else {
                    Passenger::create($data);
                }
            } else {
                $user = Driver::where('phone', $reg->phone)->orWhere('telegram_id', $chatId)->first();
                if ($user) {
                    $user->update($data);
                } else {
                    Driver::create($data);
                }
            }

            $reg->update(['step' => 'done']);
            $telegram->sendMessage($chatId, "✅ <b>Ro'yxatdan muvaffaqiyatli o'tdingiz!</b>\n\nIlovaga qaytib quyidagi ma'lumotlar bilan kiring:\n\n📱 Telefon: <b>{$reg->phone}</b>\n🔐 Parol: <b>{$text}</b>\n\nQayta ro'yxatdan o'tish uchun /start bosing.");
            return response('ok');
        }

        $telegram->sendMessage($chatId, "Qayta ro'yxatdan o'tish uchun /start bosing.");
        return response('ok');
    }
}
