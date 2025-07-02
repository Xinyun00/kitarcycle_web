<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Point;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\RewardRedemption;

class PointsController extends Controller
{
    /**
     * Get current points for a user (can increase/decrease)
     */
    public function getCurrentPoints($userId)
    {
        $user = User::findOrFail($userId);
        return response()->json([
            'user_id' => $user->id,
            'name' => $user->name,
            'current_points' => $user->current_points
        ]);
    }

    /**
     * Get leaderboard (top 3 users by total points earned)
     */
    public function getLeaderboard()
    {
        $topUsers = Point::getTopUsers(3);

        // Format the response
        $leaderboard = $topUsers->map(function ($user) {
            return [
                'name' => $user->name,
                'total_points_earned' => (int)$user->total_points_earned
            ];
        });

        return response()->json([
            'leaderboard' => $leaderboard
        ]);
    }

    /**
     * Get monthly points earned for a user
     */
    public function getMonthlyPoints($userId, Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $points = Point::getMonthlyPoints($userId, $month);

        return response()->json([
            'user_id' => $userId,
            'month' => $month,
            'points_earned' => $points
        ]);
    }

    /**
     * Get points history for a user
     */
    public function getPointsHistory($userId)
    {
        $points = Point::with('pickup.category')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'user_id' => $userId,
            'points_history' => $points
        ]);
    }

    /**
     * Get a unified points history for a user, combining earned and spent.
     */
    public function getUnifiedHistory($userId)
    {
        // 1. Fetch points earned from the 'points' table
        $pointsEarned = collect(Point::where('user_id', $userId)->get()->map(function ($point) {
            return [
                'type' => 'earned',
                'points' => (float) $point->points_earned,
                'description' => 'Points from pickup', // Or be more specific if you have data
                'date' => $point->created_at->toIso8601String(),
            ];
        }));

        // 2. Fetch points spent from the 'reward_redemptions' table
        $pointsSpent = collect(RewardRedemption::where('user_id', $userId)->get()->map(function ($redemption) {
            return [
                'type' => 'spent',
                // Make points negative to indicate they were spent
                'points' => -(float) $redemption->total_points_spent,
                'description' => 'Reward Redemption',
                'date' => $redemption->created_at->toIso8601String(),
            ];
        }));

        // 3. Merge the two collections
        $unifiedHistory = $pointsEarned->merge($pointsSpent);

        // 4. Sort the merged collection by date in descending order
        $sortedHistory = $unifiedHistory->sortByDesc('date')->values();

        // 5. Return the unified history as a JSON response
        return response()->json($sortedHistory);
    }
}
