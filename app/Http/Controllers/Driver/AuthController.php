<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $driver = Driver::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        $token = $driver->createToken('driver-token', expiresAt: now()->addMonths(3));

        return response()->json([
            'driver' => $driver,
            'token' => $token->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $driver = Driver::where('phone', $request->phone)->first();

        if (!$driver || !Hash::check($request->password, $driver->password)) {
            return response()->json([
                'message' => 'Telefon raqam yoki parol noto\'g\'ri',
            ], 401);
        }

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
