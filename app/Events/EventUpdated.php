<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventUpdated
{
    use Dispatchable, SerializesModels;

    public $event;
    public $changes;

    public function __construct(Event $event, array $changes = [])
    {
        $this->event = $event;
        $this->changes = $changes;
    }
}
