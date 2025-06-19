<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverSchedule;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    // Get all drivers for a specific organizer
    public function index($organizerId)
    {
        $drivers = Driver::with('availableSchedules')
            ->where('organizer_id', $organizerId)
            ->get();

        return response()->json($drivers);
    }

    // Store a new driver and availability schedules
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'contact_number' => 'required|string',
            'plate_number' => 'required|string|unique:drivers,plate_number',
            'organizer_id' => 'required|exists:organizers,id',
            'schedules' => 'array',
            'schedules.*.date' => 'required|date_format:Y-m-d',
            'schedules.*.time' => 'required',
        ]);

        $driver = Driver::create([
            'name' => $request->name,
            'contact_number' => $request->contact_number,
            'plate_number' => $request->plate_number,
            'organizer_id' => $request->organizer_id,
        ]);

        if ($request->has('schedules')) {
            foreach ($request->schedules as $schedule) {
                DriverSchedule::create([
                    'driver_id' => $driver->id,
                    'date' => $schedule['date'],
                    'time' => $schedule['time'],
                    'status' => 'available',
                ]);
            }
        }

        $driver->load('schedules');

        return response()->json([
            'message' => 'Driver created successfully',
            'driver' => $driver
        ]);
    }

    // Show a specific driver and schedules
    public function show($id)
    {
        $driver = Driver::with('availableSchedules')->findOrFail($id);

        if ($driver->availableSchedules->isEmpty()) {
            return response()->json([
                'driver' => $driver,
                'message' => 'All slots have been fully booked.',
            ]);
        }

        return response()->json($driver);
    }

    // Update driver info and schedules
    public function update(Request $request, $id)
    {
        $driver = Driver::findOrFail($id);

        // Validate incoming request
        $request->validate([
            'name' => 'required|string',
            'contact_number' => 'required|string',
            'plate_number' => 'required|string|unique:drivers,plate_number,' . $id,
            'organizer_id' => 'required|exists:organizers,id', // Add organizer validation
            'schedules' => 'array',
            'schedules.*.date' => 'required|date_format:Y-m-d',
            'schedules.*.time' => 'required',
        ]);

        // Update the driver's basic information
        $driver->update([
            'name' => $request->name,
            'contact_number' => $request->contact_number,
            'plate_number' => $request->plate_number,
            'organizer_id' => $request->organizer_id,  // Add organizer field update
        ]);

        // If schedules are provided, update the schedules
        if ($request->has('schedules')) {
            // Delete existing schedules for the driver
            DriverSchedule::where('driver_id', $driver->id)->delete();

            // Create new schedules for the driver
            foreach ($request->schedules as $schedule) {
                DriverSchedule::create([
                    'driver_id' => $driver->id,
                    'date' => $schedule['date'],
                    'time' => $schedule['time'],
                    'status' => 'available', // Set default status
                ]);
            }
        }

        // Reload the driver with schedules after the update
        $driver->load(['schedules' => function ($query) {
            $query->where('status', '!=', 'booked');
        }]);

        // Return the updated driver information as a response
        return response()->json([
            'message' => 'Driver updated successfully',
            'driver' => $driver,
        ]);
    }

    public function updateSchedules(Request $request, $id)
    {
        // Find the driver by ID
        $driver = Driver::findOrFail($id);

        // Validate the request for schedules
        $request->validate([
            'schedules' => 'required|array',
            'schedules.*.id' => 'required|exists:driver_schedules,id', // Make sure we provide the schedule ID for updates
            'schedules.*.date' => 'required|date_format:Y-m-d',
            'schedules.*.time' => 'required|string', // or other validation for time format
        ]);

        // Update existing schedules
        foreach ($request->schedules as $schedule) {
            // Find the schedule by its ID
            $existingSchedule = DriverSchedule::findOrFail($schedule['id']);

            // Update the schedule data
            $existingSchedule->update([
                'date' => $schedule['date'],
                'time' => $schedule['time'],
                'status' => 'available', // Or modify status based on your business logic
            ]);
        }

        // Reload the driver with updated schedules
        $driver->load(['schedules' => function ($query) {
            $query->where('status', '!=', 'booked');
        }]);

        // Return a response with the updated driver and schedules
        return response()->json([
            'message' => 'Driver schedules updated successfully',
            'driver' => $driver,
        ]);
    }

    // Book one or more driver schedules
    public function bookSchedule(Request $request)
    {
        $request->validate([
            'schedule_ids' => 'required|array',
            'schedule_ids.*' => 'required|exists:driver_schedules,id',
        ]);

        foreach ($request->schedule_ids as $id) {
            $schedule = DriverSchedule::findOrFail($id);

            // Optional: check if already booked
            if ($schedule->status === 'booked') {
                continue; // or throw an error depending on your logic
            }

            $schedule->update([
                'status' => 'booked',
            ]);
        }

        return response()->json([
            'message' => 'Selected schedule(s) booked successfully.'
        ]);
    }

    // Delete driver and their schedules
    public function destroy($id)
    {
        $driver = Driver::findOrFail($id);
        $driver->delete();

        return response()->json(['message' => 'Driver deleted successfully']);
    }
}
