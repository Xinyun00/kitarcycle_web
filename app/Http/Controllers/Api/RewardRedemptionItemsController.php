<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\reward_redemption_items;
use Illuminate\Http\Request;

class RewardRedemptionItemsController extends Controller
{
    public function index()
    {
        $items = reward_redemption_items::with('reward', 'redemption')->get();
        return response()->json($items);
    }

    public function show($id)
    {
        $item = reward_redemption_items::with('reward', 'redemption')->findOrFail($id);
        return response()->json($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reward_redemption_id' => 'required|exists:reward_redemptions,id',
            'reward_id' => 'required|exists:rewards,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $item = reward_redemption_items::create($validated);

        return response()->json($item, 201);
    }

    public function update(Request $request, $id)
    {
        $item = reward_redemption_items::findOrFail($id);

        $validated = $request->validate([
            'quantity' => 'sometimes|required|integer|min:1',
        ]);

        $item->update($validated);

        return response()->json($item);
    }

    public function destroy($id)
    {
        $item = reward_redemption_items::findOrFail($id);
        $item->delete();

        return response()->json(null, 204);
    }
}
