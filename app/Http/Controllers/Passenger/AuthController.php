<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Models\TelegramRegistration;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function verifyOtp(Request $request)
    {
        $reg = TelegramRegistration::where('role', 'passenger')
            ->where('otp', $request->otp)
            ->where('otp_expires_at', '>', now())
            ->first();

        if (!$reg) {
            return response()->json([
                'message' => 'Kod noto\'g\'ri yoki eskirgan. Telegramda yangi kod oling.',
            ], 401);
        }

        $passenger = Passenger::where('phone', $reg->phone)->first();

        if (!$passenger) {
            return response()->json([
                'message' => 'Foydalanuvchi topilmadi. Avval Telegram botga /start yuboring.',
            ], 404);
        }

        if ($passenger->is_blocked) {
            return response()->json([
                'message' => 'Sizning hisobingiz bloklangan.',
            ], 403);
        }

        // Обнуляем OTP
        $reg->update(['otp' => null, 'otp_expires_at' => null]);

        // Удаляем старые токены, выдаём новый
        $passenger->tokens()->delete();
        $token = $passenger->createToken('passenger-token', expiresAt: now()->addMonths(3));

        return response()->json([
            'passenger' => $passenger,
            'token' => $token->plainTextToken,
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'passenger' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $passenger = $request->user();
        $passenger->update($request->only(['name', 'phone']));

        return response()->json([
            'passenger' => $passenger,
        ]);
    }

    public function updateFcmToken(Request $request)
    {
        $request->user()->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'FCM token updated']);
    }

    public function logout(Request $request)
    {
        $request->user()->update(['fcm_token' => null]);
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Tizimdan chiqildi',
        ]);
    }
}
