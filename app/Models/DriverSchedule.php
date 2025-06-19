<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'date',
        'time',
        'status',
    ];

    /**
     * Get the driver that owns the schedule.
     */
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function pickups()
    {
        return $this->hasMany(Pickup::class, 'schedule_id');
    }
}
