<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\NotificationService;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class EventObserver
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event)
    {
        // Get all users who are subscribed to this event
        $subscribedUsers = Subscription::where('event_id', $event->id)
            ->with('user')
            ->get()
            ->pluck('user');

        // Send notification to each subscribed user
        foreach ($subscribedUsers as $user) {
            $this->notificationService->createEventNotification(
                $user,
                $event->eventTitle,
                'Event Created', // CORRECTED: Use title case
                [
                    'action' => 'Event Created',
                    'event_id' => $event->id,
                    'event_title' => $event->eventTitle,
                    'event_details' => $event->eventDetails,
                    'location' => $event->location,
                    'start_date' => $event->startDate,
                    'end_date' => $event->endDate
                ]
            );
        }
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event)
    {
        // Get the array of attributes that were just changed
        $changes = $event->getChanges();

        // Check if the 'status' attribute was changed to 'cancelled'
        if (isset($changes['status']) && $changes['status'] === 'cancelled') {

            // --- THIS IS A CANCELLATION ---
            $subscribedUsers = Subscription::where('event_id', $event->id)
                ->with('user')
                ->get()
                ->pluck('user');

            foreach ($subscribedUsers as $user) {
                if ($user) {
                    $this->notificationService->createEventNotification(
                        $user,
                        $event->eventTitle,
                        'Event Cancelled', // CORRECTED: Use title case
                        [
                            'action' => 'Event Cancelled',
                            'event_id' => $event->id,
                            'event_title' => $event->eventTitle,
                            'reason' => $event->cancellation_reason ?? 'The event has been cancelled.'
                        ]
                    );
                }
            }
        } elseif (!empty($changes)) {

            // --- THIS IS A REGULAR UPDATE ---
            $subscribedUsers = Subscription::where('event_id', $event->id)
                ->with('user')
                ->get()
                ->pluck('user');

            foreach ($subscribedUsers as $user) {
                if ($user) {
                    $this->notificationService->createEventNotification(
                        $user,
                        $event->eventTitle,
                        'Event Updated', // CORRECTED: Use title case
                        [
                            'action' => 'Event Updated',
                            'event_id' => $event->id,
                            'event_title' => $event->eventTitle,
                            'changes' => array_keys($changes),
                            'location' => $event->location,
                            'start_date' => $event->startDate,
                            'end_date' => $event->endDate
                        ]
                    );
                }
            }
        }
    }

    // /**
    //  * Handle the Event "updating" event (before update).
    //  * This is used to detect status changes to 'cancelled'
    //  */
    // public function updating(Event $event)
    // {
    //     // Check if status is being changed to cancelled
    //     if ($event->isDirty('status') && $event->status === 'cancelled') {
    //         try {
    //             $reason = $event->cancellation_reason ?? null;
    //             $this->notificationService->sendEventCancelledNotification($event, $reason);
    //             Log::info("Event cancelled notification sent for event: {$event->id}");
    //         } catch (\Exception $e) {
    //             Log::error("Failed to send event cancelled notification: " . $e->getMessage());
    //         }
    //     }
    // }

    /**
     * Handle the Event "deleting" event.
     */
    public function deleting(Event $event)
    {
        // Get all users who are subscribed to this event
        $subscribedUsers = Subscription::where('event_id', $event->id)
            ->with('user')
            ->get()
            ->pluck('user');

        // Send notification to each subscribed user
        foreach ($subscribedUsers as $user) {
            $this->notificationService->createEventNotification(
                $user,
                $event->eventTitle,
                'Event Cancelled', // CORRECTED: Use title case
                [
                    'action' => 'Event Cancelled',
                    'event_id' => $event->id,
                    'event_title' => $event->eventTitle,
                    'reason' => $event->cancellation_reason ?? 'No reason provided'
                ]
            );
        }
    }
}
