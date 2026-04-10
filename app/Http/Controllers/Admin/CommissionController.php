<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;

class CommissionController extends Controller
{
    public function index()
    {
        $commissions = Commission::with('driver', 'driverTrip', 'passengerTrip')
            ->latest()
            ->paginate(10);

        return response()->json($commissions);
    }
}
