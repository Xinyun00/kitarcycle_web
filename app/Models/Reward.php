<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\RewardCartItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\RewardRedemptionItem;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Reward extends Model
{
    use HasFactory;

    protected $table = 'rewards';

    protected $fillable = [
        'name',
        'description',
        'points_required',
        'stock',
        'image',
    ];

    protected $appends = ['image_url'];

    /**
     * Get the full URL for the reward's image.
     *
     * This creates a new 'image_url' attribute in your JSON responses.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['image']
                ? url($attributes['image'])
                : null
        );
    }

    public function cartItems()
    {
        return $this->hasMany(RewardCartItem::class);
    }

    public function redemptionItems()
    {
        return $this->hasMany(RewardRedemptionItem::class);
    }
}
