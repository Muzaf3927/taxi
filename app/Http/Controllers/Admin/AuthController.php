<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        if ($request->secret_key !== env('ADMIN_SECRET_KEY')) {
            return response()->json([
                'message' => 'Maxfiy kalit noto\'g\'ri',
            ], 403);
        }

        $admin = Admin::create([
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        $token = $admin->createToken('admin-token', expiresAt: now()->addMonths(3));

        return response()->json([
            'admin' => $admin,
            'token' => $token->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $admin = Admin::where('phone', $request->phone)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'message' => 'Telefon raqam yoki parol noto\'g\'ri',
            ], 401);
        }

        $admin->tokens()->delete();

        $token = $admin->createToken('admin-token', expiresAt: now()->addMonths(3));

        return response()->json([
            'admin' => $admin,
            'token' => $token->plainTextToken,
        ]);
    }
}
