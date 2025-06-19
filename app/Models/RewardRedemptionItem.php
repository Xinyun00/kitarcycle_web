<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\RewardRedemption;
use App\Models\Reward;

class RewardRedemptionItem extends Model
{
    protected $fillable = [
        'reward_redemption_id',
        'reward_id',
        'quantity',
    ];

    public function redemption()
    {
        return $this->belongsTo(RewardRedemption::class, 'reward_redemption_id');
    }

    public function reward()
    {
        return $this->belongsTo(Reward::class);
    }
}
