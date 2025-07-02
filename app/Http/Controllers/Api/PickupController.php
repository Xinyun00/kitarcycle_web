<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pickup;
use App\Models\DriverSchedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Point;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use App\Services\NotificationService;

class PickupController extends Controller
{
    /**
     * Display a listing of pickups (paginated).
     */
    public function index()
    {
        return response()->json(
            Pickup::with(['organizer', 'driver', 'schedule', 'user', 'category'])->get()
        );
    }

    /**
     * Store a newly created pickup.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'organizer_id' => 'required|exists:organizers,id',
            'category_id' => 'required|exists:categories,id',
            'address' => 'required|string',
            'estimated_weight' => 'required|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'driver_id' => 'required|exists:drivers,id',
            'schedule_id' => 'required|exists:driver_schedules,id,status,available',
        ]);

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('kitarcycle/pickups', 'public');
                $imagePaths[] = $path;
            }
        }

        try {
            DB::beginTransaction();

            $schedule = DriverSchedule::findOrFail($validated['schedule_id']);
            $schedule->status = 'booked';
            $schedule->save();

            $pickup = Pickup::create([
                'user_id' => $validated['user_id'],
                'organizer_id' => $validated['organizer_id'],
                'category_id' => $validated['category_id'],
                'address' => $validated['address'],
                'estimated_weight' => $validated['estimated_weight'],
                'height' => $validated['height'] ?? null,
                'width' => $validated['width'] ?? null,
                'driver_id' => $validated['driver_id'],
                'schedule_id' => $validated['schedule_id'],
                'images' => $imagePaths, // Store as array (JSON)
            ]);

            $recycler = User::find($validated['user_id']);
            $organizer = Organizer::find($validated['organizer_id']);

            $notificationService = app(NotificationService::class);
            $notificationService->sendPickupRequestNotification($pickup, $recycler, $organizer);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pickup request created successfully',
                'pickup' => $pickup->load(['category', 'user', 'organizer', 'schedule']),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            // If images were saved during a failed transaction, delete them.
            foreach ($imagePaths as $imagePath) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($imagePath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($imagePath);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create pickup request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing pickup.
     */
    public function update(Request $request, $id)
    {
        $pickup = Pickup::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'address' => 'sometimes|string',
            'driver_id' => 'sometimes|exists:drivers,id',
            'schedule_id' => 'sometimes|exists:driver_schedules,id',
            'images' => 'sometimes|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'estimated_weight' => 'sometimes|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
        ]);

        $imagePaths = $pickup->images ?? [];

        if ($request->hasFile('images')) {
            // Optionally: delete old images if you want to replace them
            // foreach ($imagePaths as $oldPath) {
            //     \Storage::disk('public')->delete($oldPath);
            // }
            foreach ($request->file('images') as $image) {
                $path = $image->store('kitarcycle/pickups', 'public');
                $imagePaths[] = $path;
            }
        }

        $pickup->images = $imagePaths;
        $pickup->fill($request->only(['category_id', 'address', 'estimated_weight', 'driver_id', 'schedule_id', 'height', 'width']));
        $pickup->save();

        return response()->json([
            'success' => true,
            'message' => 'Pickup updated successfully!',
            'pickup' => $pickup->load(['organizer', 'driver', 'schedule', 'user', 'category']),
        ], 200);
    }

    /**
     * Calculate points based on weight, category and user's tier multiplier
     */
    private function calculatePoints($weight, $category, $user)
    {
        // Round weight to avoid floating point issues
        $weight = round($weight, 2);

        // Get base points from weight and category points_per_kg
        $basePoints = floor($weight * $category->points_per_kg);

        // Apply tier multiplier if user has a tier
        $multiplier = $user->tierLevel ? $user->tierLevel->multiplier : 1.0;
        $finalPoints = floor($basePoints * $multiplier);

        return $finalPoints;
    }

    /**
     * Calculate pickup points.
     */
    public function calculatePickupPoints($id)
    {
        $pickup = Pickup::with(['category', 'user.tierLevel'])->find($id);
        if (!$pickup) {
            return response()->json(['message' => 'Pickup not found'], 404);
        }
        if ($pickup->actual_weight === null) {
            return response()->json(['message' => 'Actual weight not recorded yet.'], 422);
        }
        $points = $this->calculatePoints($pickup->actual_weight, $pickup->category, $pickup->user);
        return response()->json([
            'pickup_id' => $pickup->id,
            'actual_weight' => $pickup->actual_weight,
            'points_awarded' => $points,
            'points_per_kg' => $pickup->category->points_per_kg,
            'category_name' => $pickup->category->name,
            'tier_multiplier' => $pickup->user->tierLevel ? $pickup->user->tierLevel->multiplier : 1.0,
            'tier_name' => $pickup->user->tierLevel ? $pickup->user->tierLevel->name : 'No Tier'
        ]);
    }

    /**
     * Update actual weight and complete pickup.
     */
    public function updateWeight(Request $request, $id)
    {
        $request->validate([
            'actual_weight' => 'required|numeric|min:0',
        ]);

        $pickup = Pickup::with(['category', 'user.tierLevel'])->find($id);

        if (!$pickup) {
            return response()->json(['message' => 'Pickup not found'], 404);
        }

        if ($pickup->status !== 'In Progress') {
            return response()->json(['message' => 'Actual weight can only be updated when pickup is In Progress.'], 422);
        }

        DB::beginTransaction();
        try {
            $pickup->actual_weight = $request->actual_weight;
            $pickup->status = 'Completed';
            $pickup->save();

            // Calculate and add points using category points_per_kg and tier multiplier
            $points = $this->calculatePoints($request->actual_weight, $pickup->category, $pickup->user);
            Point::addPoints(
                $pickup->user_id,
                $pickup->id,
                $points,
                $request->actual_weight,
                $pickup->category->points_per_kg,
                'pickup_completion',
                "Completed pickup #{$pickup->id} with {$request->actual_weight}kg of {$pickup->category->name}",
                $pickup->organizer_id
            );

            // Send notification to recycler about completion
            app(NotificationService::class)->createPickupStatusNotification(
                $pickup->user,
                'Completed',
                [
                    'pickup_id' => $pickup->id,
                    'actual_weight' => $request->actual_weight,
                    'points_awarded' => $points,
                    'organizer_name' => $pickup->organizer->name
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Actual weight updated successfully and status marked as completed.',
                'pickup' => $pickup->load(['category']),
                'points_awarded' => $points,
                'tier_multiplier' => $pickup->user->tierLevel ? $pickup->user->tierLevel->multiplier : 1.0,
                'tier_name' => $pickup->user->tierLevel ? $pickup->user->tierLevel->name : 'No Tier'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update weight.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show all pickups for a given user.
     */
    public function getPickupsByUser($userId)
    {
        try {
            $pickups = Pickup::with(['organizer', 'driver', 'schedule'])
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($pickups->isEmpty()) {
                return response()->json(['message' => 'No pickups found for this user'], 404);
            }

            return response()->json($pickups, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching pickups for user: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch pickups'], 500);
        }
    }

    /**
     * Show a single pickup.
     */
    public function show($id)
    {
        $pickup = Pickup::with(['organizer', 'driver', 'schedule', 'user', 'category'])->find($id);

        if (!$pickup) {
            return response()->json(['message' => 'Pickup not found'], 404);
        }

        return response()->json($pickup);
    }

    /**
     * Update pickup status (with optional authorization check).
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:Pending,In Progress,Completed,Rejected',
                'reason' => 'required_if:status,Rejected|nullable|string',
            ]);

            $pickup = Pickup::findOrFail($id);
            $oldStatus = $pickup->status;
            $pickup->status = $validated['status'];
            $pickup->save();

            app(NotificationService::class)->sendPickupStatusUpdateNotification(
                $pickup,
                $pickup->user,
                $validated['status'],
                $oldStatus
            );
            return response()->json([
                'success' => true,
                'message' => 'Pickup status updated successfully',
                'pickup' => $pickup->load(['category', 'user', 'organizer']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update pickup status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reject a pickup.
     */
    public function rejectPickup(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        $pickup = Pickup::find($id);

        if (!$pickup) {
            return response()->json(['message' => 'Pickup not found'], 404);
        }

        if ($pickup->status !== 'Pending') {
            return response()->json(['message' => 'Only pending pickups can be rejected.'], 422);
        }

        $pickup->status = 'Rejected';
        $pickup->rejection_reason = $request->input('rejection_reason', 'No reason provided');

        if ($pickup->schedule) {
            $pickup->schedule->update(['status' => 'available']);
        }

        $pickup->save();

        return response()->json([
            'message' => 'Pickup rejected successfully',
            'pickup' => $pickup->load(['organizer', 'driver', 'schedule', 'user', 'category']),
        ]);
    }

    /**
     * Cancel a pickup.
     */
    public function cancelPickup(Request $request, $id)
    {
        try {
            $validated = $request->validate(['reason' => 'nullable|string']);
            $pickup = Pickup::findOrFail($id);
            $pickup->status = 'Cancelled';
            $pickup->cancellation_reason = $validated['reason'] ?? null;
            $pickup->save();

            if ($pickup->schedule && $pickup->schedule->status === 'booked') {
                $pickup->schedule->update(['status' => 'available']);
            }

            app(NotificationService::class)->sendPickupCancellationNotification(
                $pickup,
                $pickup->user,
                $pickup->organizer,
                $validated['reason'] ?? null
            );
            return response()->json([
                'success' => true,
                'message' => 'Pickup cancelled successfully',
                'pickup' => $pickup->load(['category', 'user', 'organizer']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to cancel pickup: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get total recyclers and total completed collections for a given organizer.
     */
    public function getOrganizerStats($organizerId)
    {
        // Count unique users who have pickups under this organizer
        $totalRecyclers = Pickup::where('organizer_id', $organizerId)
            ->distinct('user_id')
            ->count('user_id');

        // Count completed pickups under this organizer
        $totalCollections = Pickup::where('organizer_id', $organizerId)
            ->where('status', 'Completed')
            ->count();

        return response()->json([
            'organizer_id' => $organizerId,
            'total_recyclers' => $totalRecyclers,
            'total_collections' => $totalCollections
        ]);
    }

    /**
     * Get total completed pickups per month for a given organizer.
     */
    public function getMonthlyCollections($organizerId)
    {
        $monthlyCollections = Pickup::selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->where('organizer_id', $organizerId)
            ->where('status', 'Completed')
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->orderBy(DB::raw('MONTH(created_at)'))
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'month_name' => date('F', mktime(0, 0, 0, $item->month, 1)),
                    'total' => $item->total
                ];
            });

        return response()->json($monthlyCollections);
    }

    /**
     * Delete a pickup.
     */
    public function destroy($id)
    {
        $pickup = Pickup::find($id);

        if (!$pickup) {
            return response()->json(['message' => 'Pickup not found'], 404);
        }

        // Free up the schedule if booked
        if ($pickup->schedule) {
            $pickup->schedule->update(['status' => 'available']);
        }

        // Delete the image
        if ($pickup->images) {
            Storage::disk('public')->delete($pickup->images);
        }

        $pickup->delete();

        return response()->json(['message' => 'Pickup deleted successfully']);
    }
}
