<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\RewardRedemptionItem;

class RewardRedemption extends Model
{
    protected $fillable = [
        'user_id',
        'total_points_spent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(RewardRedemptionItem::class);
    }
}
