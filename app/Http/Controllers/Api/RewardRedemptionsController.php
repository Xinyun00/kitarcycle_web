<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\reward_redemptions;
use Illuminate\Http\Request;

class RewardRedemptionsController extends Controller
{
    public function index()
    {
        $redemptions = reward_redemptions::with('items.reward')->get();
        return response()->json($redemptions);
    }

    public function show($id)
    {
        $redemption = reward_redemptions::with('items.reward')->findOrFail($id);
        return response()->json($redemption);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'total_points_spent' => 'required|integer|min:0',
            'items' => 'required|array',
            'items.*.reward_id' => 'required|exists:rewards,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $redemption = reward_redemptions::create([
            'user_id' => $validated['user_id'],
            'total_points_spent' => $validated['total_points_spent'],
        ]);

        foreach ($validated['items'] as $item) {
            $redemption->items()->create($item);
        }

        return response()->json($redemption->load('items.reward'), 201);
    }

    public function update(Request $request, $id)
    {
        $redemption = reward_redemptions::findOrFail($id);

        $validated = $request->validate([
            'total_points_spent' => 'sometimes|required|integer|min:0',
            // Usually redemptions are not updated heavily; update logic depends on business rules
        ]);

        $redemption->update($validated);

        return response()->json($redemption);
    }

    public function destroy($id)
    {
        $redemption = reward_redemptions::findOrFail($id);
        $redemption->delete();

        return response()->json(null, 204);
    }
}
