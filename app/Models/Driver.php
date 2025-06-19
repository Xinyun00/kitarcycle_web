<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_number',
        'plate_number',
        'organizer_id',
    ];

    public function pickups()
    {
        return $this->hasMany(Pickup::class);
    }

    /**
     * Get the schedules for the driver.
     */
    public function schedules()
    {
        return $this->hasMany(DriverSchedule::class);
    }

    public function availableSchedules()
    {
        return $this->hasMany(DriverSchedule::class)->where('status', '!=', 'booked');
    }

    /**
     * Get the organizer that owns the driver.
     * (Optional: only if you have an Organizer model and relationship)
     */
    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }
}
