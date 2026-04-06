<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\Request;

class CarController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'number' => ['required', 'string', 'size:8', 'regex:/^[0-9]{2}[A-Z]{1}[0-9]{3}[A-Z]{2}$/'],
            'color' => ['required', 'string', 'max:20'],
            'model' => ['required', 'string', 'max:20'],
        ], [
            'number.regex' => 'Mashina raqami noto\'g\'ri formatda. To\'g\'ri format: 01A234BC',
            'number.size' => 'Mashina raqami 8 ta belgidan iborat bo\'lishi kerak',
            'color.max' => 'Rang nomi 20 ta belgidan oshmasligi kerak',
            'model.max' => 'Model nomi 20 ta belgidan oshmasligi kerak',
        ]);

        $driver = $request->user();

        $car = Car::updateOrCreate(
            ['driver_id' => $driver->id],
            [
                'number' => strtoupper($request->number),
                'color' => $request->color,
                'model' => $request->model,
            ]
        );

        return response()->json([
            'car' => $car,
        ], 201);
    }
}
