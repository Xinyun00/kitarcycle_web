<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventCancelled
{
    use Dispatchable, SerializesModels;

    public $event;
    public $reason;

    public function __construct(Event $event, ?string $reason = null)
    {
        $this->event = $event;
        $this->reason = $reason;
    }
}
