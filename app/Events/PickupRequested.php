<?php

namespace App\Events;

use App\Models\Pickup;
use App\Models\User;
use App\Models\Organizer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PickupRequested
{
    use Dispatchable, SerializesModels;

    public $pickup;
    public $recycler;
    public $organizer;

    public function __construct(Pickup $pickup, User $recycler, Organizer $organizer)
    {
        $this->pickup = $pickup;
        $this->recycler = $recycler;
        $this->organizer = $organizer;
    }
}
