<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\TierLevelController;

class Point extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pickup_id',
        'points_earned',
        'actual_weight',
        'points_per_kg',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pickup()
    {
        return $this->belongsTo(Pickup::class);
    }

    /**
     * Add points for a completed pickup
     */
    public static function addPoints(
        int $userId,
        int $pickupId,
        float $pointsEarned,
        float $actualWeight,
        float $pointsPerKg,
        string $type = 'pickup_completion', // Default value
        string $description = '',
        ?int $organizerId = null // Default value
    ): bool {
        return DB::transaction(function () use (
            $userId,
            $pickupId,
            $pointsEarned,
            $actualWeight,
            $pointsPerKg,
            $type,
            $description,
            $organizerId
        ) {
            try {
                // 1. Create the points record in the 'points' table
                self::create([
                    'user_id' => $userId,
                    'pickup_id' => $pickupId,
                    'points_earned' => $pointsEarned,
                    'actual_weight' => $actualWeight,
                    'points_per_kg' => $pointsPerKg,
                    'type' => $type, // Store the type
                    'description' => $description, // Store the description
                    'organizer_id' => $organizerId, // Store the organizer ID
                ]);

                // 2. Get the user and update their points in the 'users' table
                $user = User::find($userId);

                if (!$user) {
                    Log::error("User not found when trying to add points. User ID: {$userId}");
                    return false; // This will trigger a rollback
                }

                // Increment total_points_earned and current_points
                $user->increment('total_points_earned', $pointsEarned); // Uses the increment method for atomic update
                $user->increment('current_points', $pointsEarned); // Uses the increment method for atomic update
                // No need for $user->save() after incrementing

                $tierLevelController = new TierLevelController();
                $tierLevelController->assignTierToUser($user);

                Log::info("Points successfully added for user {$userId}: {$pointsEarned} points.");
                return true;
            } catch (\Exception $e) {
                Log::error("Error adding points for user {$userId}, pickup {$pickupId}: " . $e->getMessage());
                // The transaction will automatically rollback if an exception occurs
                return false;
            }
        });
    }

    /**
     * Get monthly points for a user
     */
    public static function getMonthlyPoints($userId, $month = null)
    {
        $month = $month ?? now()->format('Y-m');
        return self::where('user_id', $userId)
            ->whereYear('created_at', substr($month, 0, 4))
            ->whereMonth('created_at', substr($month, 5, 2))
            ->sum('points_earned');
    }

    /**
     * Get top users by total points earned
     */
    public static function getTopUsers($limit = 3)
    {
        return User::select('users.id', 'users.name')
            ->selectRaw('(SELECT SUM(points_earned) FROM points WHERE points.user_id = users.id) as total_points_earned')
            ->orderBy('total_points_earned', 'desc')
            ->take($limit)
            ->get();
    }
}
