<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class subscription extends Model
{
    protected $table = 'subscriptions';

    // Allow mass assignment for these fields
    protected $fillable = [
        'user_id',
        'event_id',
        'calendar_event_id',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
    ];


    // If you want to define relationships (optional but recommended)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function isPastEvent()
    {
        return $this->event && Carbon::parse($this->event->endDate)->isPast();
    }
}
