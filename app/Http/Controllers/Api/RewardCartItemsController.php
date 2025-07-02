<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RewardCartItem;
use Illuminate\Http\Request;

class RewardCartItemsController extends Controller
{
    public function index(Request $request)
    {
        // Validate that a user_id is provided in the request URL
        $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);

        $userId = $request->input('user_id');

        // Now, fetch only the cart items for that specific user
        $cartItems = RewardCartItem::with('Reward')
            ->where('user_id', $userId)
            ->get();

        return response()->json($cartItems);
    }

    public function show($id)
    {
        $cartItem = RewardCartItem::with('reward', 'user')->findOrFail($id);
        return response()->json($cartItem);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reward_id' => 'required|exists:rewards,id',
            'quantity' => 'required|integer|min:1',
        ]);

        // Check if the item is already in the cart to avoid duplicates
        $existingItem = RewardCartItem::where('user_id', $validated['user_id'])
            ->where('reward_id', $validated['reward_id'])
            ->first();

        if ($existingItem) {
            $existingItem->quantity += $validated['quantity'];
            $existingItem->save();

            return response()->json($existingItem, 200); // Or return an error if you don't want duplicates
        }

        $cartItem = RewardCartItem::create($validated);

        return response()->json($cartItem, 201);
    }

    public function update(Request $request, $id)
    {
        $cartItem = RewardCartItem::findOrFail($id);

        $validated = $request->validate([
            'quantity' => 'sometimes|required|integer|min:1',
        ]);

        $cartItem->update($validated);

        return response()->json($cartItem);
    }

    public function destroy($id)
    {
        $cartItem = RewardCartItem::findOrFail($id);
        $cartItem->delete();

        return response()->json(null, 204);
    }
}
