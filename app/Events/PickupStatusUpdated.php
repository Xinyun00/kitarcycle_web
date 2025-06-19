<?php

namespace App\Events;

use App\Models\Pickup;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PickupStatusUpdated
{
    use Dispatchable, SerializesModels;

    public $pickup;
    public $recycler;
    public $newStatus;
    public $oldStatus;

    public function __construct(Pickup $pickup, User $recycler, string $newStatus, ?string $oldStatus = null)
    {
        $this->pickup = $pickup;
        $this->recycler = $recycler;
        $this->newStatus = $newStatus;
        $this->oldStatus = $oldStatus;
    }
}