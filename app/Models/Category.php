<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'points_per_kg',
    ];

    // Relationship
    public function pickups()
    {
        return $this->hasMany(Pickup::class);
    }
}
