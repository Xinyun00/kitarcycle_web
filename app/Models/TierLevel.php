<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class TierLevel extends Model
{
    use HasFactory;

    protected $table = 'tier_levels';

    protected $fillable = [
        'name',
        'point_from',
        'point_to',
        'multiplier',
        'image',
        'description',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'tier_level_id');
    }
}
