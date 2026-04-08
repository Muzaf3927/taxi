<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\TelegramRegistration;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function verifyOtp(Request $request)
    {
        $reg = TelegramRegistration::where('role', 'driver')
            ->where('otp', $request->otp)
            ->where('otp_expires_at', '>', now())
            ->first();

        if (!$reg) {
            return response()->json([
                'message' => 'Kod noto\'g\'ri yoki eskirgan. Telegramda yangi kod oling.',
            ], 401);
        }

        $driver = Driver::where('phone', $reg->phone)->first();

        if (!$driver) {
            return response()->json([
                'message' => 'Foydalanuvchi topilmadi. Avval Telegram botga /start yuboring.',
            ], 404);
        }

        if ($driver->is_blocked) {
            return response()->json([
                'message' => 'Sizning hisobingiz bloklangan.',
            ], 403);
        }

        // Обнуляем OTP
        $reg->update(['otp' => null, 'otp_expires_at' => null]);

        // Удаляем старые токены, выдаём новый
        $driver->tokens()->delete();
        $token = $driver->createToken('driver-token', expiresAt: now()->addMonths(3));

        return response()->json([
            'driver' => $driver,
            'token' => $token->plainTextToken,
        ]);
    }

    public function profile(Request $request)
    {
        $driver = $request->user();
        $driver->load('cars');

        return response()->json([
            'driver' => $driver,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $driver = $request->user();
        $driver->update($request->only(['name', 'phone']));

        return response()->json([
            'driver' => $driver,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Tizimdan chiqildi',
        ]);
    }
}
