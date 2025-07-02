<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Pickup;
use App\Models\TierLevel;
use App\Models\RewardCartItem;
use App\Models\reward_redemptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'contact_number',
        'location',
        'image',
        'total_points_earned',
        'current_points',
        'tier_level_id',
        'api_token',
        'notification_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token', // Hide API token from serialization
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'total_points_earned' => 'integer',
            'current_points' => 'integer',
            'notification_preferences' => 'array',
        ];
    }

    public function getPointsAttribute()
    {
        return $this->current_points;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            // Set default points to 0
            $user->total_points_earned = $user->total_points_earned ?? 0;
            $user->current_points = $user->current_points ?? 0;

            // Find and set the Green Seed tier level
            $greenSeedTier = TierLevel::where('name', 'Green Seed')->first();
            if ($greenSeedTier) {
                $user->tier_level_id = $greenSeedTier->id;
            }
        });
    }

    public function pickups()
    {
        return $this->hasMany(Pickup::class);
    }

    public function tierLevel()
    {
        return $this->belongsTo(TierLevel::class, 'tier_level_id');
    }

    public function rewardCartItems()
    {
        return $this->hasMany(RewardCartItem::class, 'user_id');
    }

    public function rewardRedemptions()
    {
        return $this->hasMany(RewardRedemption::class);
    }
}
