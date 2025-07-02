<?php

namespace App\Listeners;

use App\Events\EventCreated;
use App\Events\EventUpdated;
use App\Events\EventCancelled;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class EventNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handleEventCreated(EventCreated $event)
    {
        $this->notificationService->sendEventCreatedNotification($event->event);
    }

    public function handleEventUpdated(EventUpdated $event)
    {
        $this->notificationService->sendEventUpdatedNotification($event->event, $event->changes);
    }

    public function handleEventCancelled(EventCancelled $event)
    {
        $this->notificationService->sendEventCancelledNotification($event->event, $event->reason);
    }
}
