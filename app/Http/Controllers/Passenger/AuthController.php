<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $passenger = Passenger::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        $token = $passenger->createToken('passenger-token', expiresAt: now()->addMonths(3));

        return response()->json([
            'passenger' => $passenger,
            'token' => $token->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $passenger = Passenger::where('phone', $request->phone)->first();

        if (!$passenger || !Hash::check($request->password, $passenger->password)) {
            return response()->json([
                'message' => 'Telefon raqam yoki parol noto\'g\'ri',
            ], 401);
        }

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

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Tizimdan chiqildi',
        ]);
    }
}
