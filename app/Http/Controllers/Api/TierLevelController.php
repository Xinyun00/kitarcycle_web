<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TierLevel;
use App\Models\User;
use Illuminate\Http\Request;

class TierLevelController extends Controller
{
    // List all tiers
    public function index()
    {
        return TierLevel::all();
    }

    // Show a single tier
    public function show(TierLevel $tierLevel)
    {
        return $tierLevel;
    }

    // Create a new tier
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:20',
            'point_from' => 'required|numeric|min:0',
            'point_to' => 'required|numeric|gt:point_from',
            'multiplier' => 'required|numeric|min:1',
            'image' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $tier = TierLevel::create($validated);

        return response()->json($tier, 201);
    }

    // Update a tier
    public function update(Request $request, TierLevel $tierLevel)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:20',
            'point_from' => 'sometimes|numeric|min:0',
            'point_to' => 'sometimes|numeric',
            'multiplier' => 'sometimes|numeric|min:1',
            'image' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $tierLevel->update($validated);

        return response()->json($tierLevel);
    }

    // Delete a tier
    public function destroy(TierLevel $tierLevel)
    {
        $tierLevel->delete();

        return response()->json(['message' => 'Tier deleted successfully.']);
    }

    // Optional: Assign tier to a user based on their points
    public function assignTierToUser(User $user)
    {
        $tier = TierLevel::where('point_from', '<=', $user->total_points_earned)
            ->where('point_to', '>=', $user->total_points_earned)
            ->first();

        if ($tier) {
            // Only update if the user's current tier is different
            if ($user->tier_level_id !== $tier->id) {
                $user->tier_level_id = $tier->id;
                $user->save();
                return response()->json([
                    'message' => 'Tier assigned.',
                    'tier' => $tier->name,
                ]);
            }
            return response()->json([
                'message' => 'User is already in the correct tier.',
                'tier' => $user->tierLevel->name ?? 'None',
            ]);
        }

        $greenSeedTier = TierLevel::where('name', 'Green Seed')->first();
        if ($greenSeedTier && $user->tier_level_id !== $greenSeedTier->id) {
            $user->tier_level_id = $greenSeedTier->id;
            $user->save();
            return response()->json([
                'message' => 'User assigned to default tier.',
                'tier' => $greenSeedTier->name,
            ]);
        }

        return response()->json(['message' => 'No matching tier found.'], 404);
    }
}
