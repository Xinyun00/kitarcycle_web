<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\RewardCartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RewardCheckoutController extends Controller
{
    public function checkout(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            // Get user and their cart items
            $user = User::findOrFail($request->user_id);
            $cartItems = RewardCartItem::with('reward')
                ->where('user_id', $user->id)
                ->get();

            if ($cartItems->isEmpty()) {
                return response()->json([
                    'message' => 'Cart is empty'
                ], 400);
            }

            // Calculate total points needed
            $totalPointsNeeded = 0;
            foreach ($cartItems as $item) {
                $totalPointsNeeded += ($item->reward->points_required * $item->quantity);
            }

            // Check if user has enough points
            if ($user->current_points < $totalPointsNeeded) {
                return response()->json([
                    'message' => 'Insufficient points',
                    'required_points' => $totalPointsNeeded,
                    'available_points' => $user->current_points
                ], 400);
            }

            // Check stock availability
            foreach ($cartItems as $item) {
                $reward = $item->reward;
                if ($reward->stock < $item->quantity) {
                    return response()->json([
                        'message' => "Insufficient stock for reward: {$reward->name}",
                        'available_stock' => $reward->stock,
                        'requested_quantity' => $item->quantity
                    ], 400);
                }
            }

            // Create redemption record
            $redemption = RewardRedemption::create([
                'user_id' => $user->id,
                'total_points_spent' => $totalPointsNeeded
            ]);

            // Create redemption items and update stock
            foreach ($cartItems as $item) {
                // Create redemption item
                $redemption->items()->create([
                    'reward_id' => $item->reward_id,
                    'quantity' => $item->quantity
                ]);

                // Update reward stock
                $reward = $item->reward;
                $reward->stock -= $item->quantity;
                $reward->save();
            }

            // Deduct points from user
            $user->current_points -= $totalPointsNeeded;
            $user->save();

            // Clear the cart
            RewardCartItem::where('user_id', $user->id)->delete();

            DB::commit();

            return response()->json([
                'message' => 'Reward redemption successful',
                'redemption' => $redemption->load('items.reward'),
                'remaining_points' => $user->current_points
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reward checkout error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to process reward redemption',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
