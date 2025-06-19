<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Organizer;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class event extends Model
{
    use HasFactory;

    protected $fillable = [
        'eventTitle',  // Add this
        'eventDetails',
        'location',
        'startDate',
        'endDate',
        'image',
        'organizer_id',
        'status',
        'cancellation_reason'
    ];

    protected $casts = [
        'startDate' => 'datetime',
        'endDate' => 'datetime',
    ];

    protected $appends = ['image_url'];

    /**
     * Get the full URL for the event's image.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['image']
                ? asset($attributes['image'])
                : null
        );
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    // Check if event is past
    public function isPast()
    {
        return Carbon::parse($this->endDate)->isPast();
    }

    // Check if event is ongoing
    public function isOngoing()
    {
        $now = Carbon::now();
        return Carbon::parse($this->startDate)->lte($now) && Carbon::parse($this->endDate)->gte($now);
    }

    // Check if event is upcoming
    public function isUpcoming()
    {
        return Carbon::parse($this->startDate)->isFuture();
    }

    // Get event status
    public function getStatus()
    {
        if ($this->isPast()) {
            return 'past';
        } elseif ($this->isOngoing()) {
            return 'ongoing';
        } else {
            return 'upcoming';
        }
    }
}
