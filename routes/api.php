<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BalanceController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\TripController as AdminTripController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Driver\AuthController as DriverAuthController;
use App\Http\Controllers\Driver\CarController;
use App\Http\Controllers\Driver\TripController as DriverTripController;
use App\Http\Controllers\Driver\BookingController as DriverBookingController;
use App\Http\Controllers\Passenger\AuthController as PassengerAuthController;
use App\Http\Controllers\Passenger\BookingController as PassengerBookingController;
use App\Http\Controllers\Passenger\TripController as PassengerTripController;

Route::post('/telegram/passenger', [TelegramWebhookController::class, 'passenger']);
Route::post('/telegram/driver', [TelegramWebhookController::class, 'driver']);

Route::prefix('admin')->group(function () {
    Route::post('/register', [AdminAuthController::class, 'register']);
    Route::post('/login', [AdminAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/driver/balance', [BalanceController::class, 'updateDriverBalance']);
        Route::post('/passenger/balance', [BalanceController::class, 'updatePassengerBalance']);
        Route::get('/commissions', [CommissionController::class, 'index']);
        Route::get('/drivers', [UserController::class, 'drivers']);
        Route::get('/passengers', [UserController::class, 'passengers']);
        Route::post('/driver/{id}/toggle-block', [UserController::class, 'toggleDriverBlock']);
        Route::post('/passenger/{id}/toggle-block', [UserController::class, 'togglePassengerBlock']);
        Route::get('/driver-trips', [AdminTripController::class, 'driverTrips']);
        Route::get('/passenger-trips', [AdminTripController::class, 'passengerTrips']);
    });
});

Route::prefix('driver')->group(function () {
    Route::post('/verify-otp', [DriverAuthController::class, 'verifyOtp']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [DriverAuthController::class, 'profile']);
        Route::post('/profile', [DriverAuthController::class, 'updateProfile']);
        Route::post('/logout', [DriverAuthController::class, 'logout']);

        Route::post('/car', [CarController::class, 'store']);
        Route::get('/my-trips', [DriverTripController::class, 'myTrips']);
        Route::get('/history', [DriverTripController::class, 'history']);
        Route::post('/trip', [DriverTripController::class, 'store']);
        Route::post('/trip/{id}/complete', [DriverTripController::class, 'complete']);
        Route::get('/passenger-trips', [DriverTripController::class, 'activePassengerTrips']);

        Route::get('/my-bookings', [DriverBookingController::class, 'myBookings']);
        Route::post('/booking', [DriverBookingController::class, 'store']);
        Route::post('/booking/{id}/accept', [DriverBookingController::class, 'accept']);
        Route::post('/booking/{id}/reject', [DriverBookingController::class, 'reject']);
        Route::post('/booking/{id}/cancel', [DriverBookingController::class, 'cancel']);
        Route::post('/booking/{id}/complete', [DriverBookingController::class, 'complete']);
    });
});

Route::prefix('passenger')->group(function () {
    Route::post('/verify-otp', [PassengerAuthController::class, 'verifyOtp']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [PassengerAuthController::class, 'profile']);
        Route::post('/profile', [PassengerAuthController::class, 'updateProfile']);
        Route::post('/logout', [PassengerAuthController::class, 'logout']);

        Route::get('/my-trips', [PassengerTripController::class, 'myTrips']);
        Route::get('/history', [PassengerTripController::class, 'history']);
        Route::post('/trip', [PassengerTripController::class, 'store']);
        Route::post('/trip/{id}/complete', [PassengerTripController::class, 'complete']);
        Route::get('/driver-trips', [PassengerTripController::class, 'activeDriverTrips']);

        Route::get('/my-bookings', [PassengerBookingController::class, 'myBookings']);
        Route::post('/booking', [PassengerBookingController::class, 'store']);
        Route::post('/booking/{id}/cancel', [PassengerBookingController::class, 'cancel']);
    });
});
