<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pickup extends Model
{
    protected $fillable = [
        'images',
        'category_id',
        'user_id',
        'organizer_id',
        'driver_id',
        'schedule_id',
        'address',
        'estimated_weight',
        'actual_weight',
        'status',
        'rejection_reason',
        'height',
        'width',
    ];

       protected $casts = [
       'images' => 'array',
   ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function schedule()
    {
        return $this->belongsTo(DriverSchedule::class);
    }


}
