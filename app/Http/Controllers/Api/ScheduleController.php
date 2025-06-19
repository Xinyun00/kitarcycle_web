<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DriverSchedule;

class ScheduleController extends Controller
{
    // Get all available time slots for a specific driver
    public function getAvailableSlots($driverId)
    {
        $schedules = DriverSchedule::where('driver_id', $driverId)
                    ->where('status', 'available')
                    ->orderBy('date')
                    ->orderBy('time')
                    ->get();

        return response()->json($schedules);
    }

    // Add a new availability schedule
    public function store(Request $request)
    {
        $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'date' => 'required|date',
            'time' => 'required',
            'status' => 'in:available,booked',
        ]);

        $schedule = DriverSchedule::create([
            'driver_id' => $request->driver_id,
            'date' => $request->date,
            'time' => $request->time,
            'status' => $request->status ?? 'available',
        ]);

        return response()->json($schedule, 201);
    }

    // Book a time slot
    public function bookSlot(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|exists:availability_schedules,id',
        ]);

        $schedule = DriverSchedule::find($request->schedule_id);
        if ($schedule->status == 'booked') {
            return response()->json(['message' => 'Slot already booked'], 400);
        }

        $schedule->status = 'booked';
        $schedule->save();

        return response()->json(['message' => 'Slot booked successfully']);
    }

    // Optional: Delete a schedule
    public function destroy($id)
    {
        $schedule = DriverSchedule::findOrFail($id);
        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted']);
    }
}
