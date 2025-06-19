<?php

namespace App\Listeners;

use App\Events\PickupRequested;
use App\Events\PickupStatusUpdated;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PickupNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handlePickupRequested(PickupRequested $event)
    {
        $this->notificationService->sendPickupRequestNotification(
            $event->pickup,
            $event->recycler,
            $event->organizer
        );
    }

    public function handlePickupStatusUpdated(PickupStatusUpdated $event)
    {
        $this->notificationService->sendPickupStatusUpdateNotification(
            $event->pickup,
            $event->recycler,
            $event->newStatus,
            $event->oldStatus
        );
    }
}