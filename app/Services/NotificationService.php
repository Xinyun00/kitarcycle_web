<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Organizer;
use Illuminate\Support\Facades\Log;
use App\Models\Subscription;
use App\Services\FirebaseNotificationService;

class NotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseNotificationService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function sendEventCreatedNotification($event)
    {
        $title = "New Event Available!";
        $message = "A new recycling event '{$event->name}' has been created. Check it out!";

        $subscribedUsers = $this->getRelevantUsersForEvent($event);

        foreach ($subscribedUsers as $user) {
            $dataPayload = [
                'action' => 'Event Created',
                'type' => Notification::TYPE_EVENT_NOTIFICATION,
                'event_id' => $event->id,
                'event_title' => $event->eventTitle
            ];

            // Send the push notification to the user's device
            $this->firebaseService->sendToUser($user->id, $title, $message, $dataPayload);

            // Create the notification record in the database for the in-app list
            Notification::create([
                'recipient_type' => 'App\\Models\\User',
                'recipient_id' => $user->id,
                'type' => Notification::TYPE_EVENT_NOTIFICATION,
                'title' => $title,
                'message' => $message,
                'data' => $dataPayload,
                'is_read' => false,
            ]);
        }
    }

    public function sendEventUpdatedNotification($event, $changes = [])
    {
        $title = "Event Updated: " . $event->eventTitle;
        $message = "The event '{$event->eventTitle}' has been updated. Check the latest details!";

        $subscribedUsers = $this->getRelevantUsersForEvent($event);

        foreach ($subscribedUsers as $user) {
            $dataPayload = [
                'action' => 'Event Updated',
                'type' => Notification::TYPE_EVENT_NOTIFICATION,
                'event_id' => $event->id,
                'event_title' => $event->eventTitle,
                'changes' => array_keys($changes),
            ];

            $this->firebaseService->sendToUser($user->id, $title, $message, $dataPayload);

            Notification::create([
                'recipient_type' => 'App\\Models\\User',
                'recipient_id' => $user->id,
                'type' => Notification::TYPE_EVENT_NOTIFICATION,
                'title' => $title,
                'message' => $message,
                'data' => $dataPayload,
                'is_read' => false,
            ]);
        }
    }

    public function sendEventCancelledNotification($event, $reason = null)
    {
        $title = "Event Cancelled: " . $event->eventTitle;
        $message = "Unfortunately, the event '{$event->eventTitle}' has been cancelled.";

        if ($reason) {
            $message .= " Reason: {$reason}";
        }

        $subscribedUsers = $this->getRelevantUsersForEvent($event);

        foreach ($subscribedUsers as $user) {
            $dataPayload = [
                'action' => 'Event Cancelled',
                'type' => Notification::TYPE_EVENT_NOTIFICATION,
                'event_id' => $event->id,
                'event_title' => $event->eventTitle,
                'reason' => $reason
            ];

            $this->firebaseService->sendToUser($user->id, $title, $message, $dataPayload);

            Notification::create([
                'recipient_type' => 'App\\Models\\User',
                'recipient_id' => $user->id,
                'type' => Notification::TYPE_EVENT_NOTIFICATION,
                'title' => $title,
                'message' => $message,
                'data' => $dataPayload,
                'is_read' => false,
            ]);
        }
    }

    public function sendPickupRequestNotification($pickup, $recycler, $organizer)
    {
        $title = "New Pickup Request";
        $message = "You have received a new pickup request from {$recycler->name} for pickup ID {$pickup->id}.";

        $recipientId = $organizer->id ?? null;
        if (is_null($recipientId)) {
            Log::error("Failed to send pickup request notification: Organizer ID is null for pickup ID {$pickup->id}");
            return false;
        }

        $dataPayload = [
            'action' => 'Pickup Requested',
            'type' => Notification::TYPE_PICKUP_REQUESTED,
            'pickup_id' => $pickup->id,
            'recycler_name' => $recycler->name,
            'category' => $pickup->category->name,
            'estimated_weight' => $pickup->estimated_weight,
            'address' => $pickup->address,
        ];

        $this->firebaseService->sendToUser($organizer->id, $title, $message, $dataPayload);

        Notification::create([
            'recipient_type' => 'App\\Models\\Organizer',
            'recipient_id' => $recipientId,
            'type' => Notification::TYPE_PICKUP_REQUESTED,
            'title' => $title,
            'message' => $message,
            'data' => $dataPayload,
            'is_read' => false,
        ]);
    }

    public function sendPickupCancellationNotification($pickup, $recycler, $organizer, $reason = null)
    {
        $title = "Pickup Request Cancelled";
        $message = "Pickup ID {$pickup->id} from {$recycler->name} has been cancelled.";
        if ($reason) {
            $message .= " Reason: {$reason}";
        }

        $recipientId = $organizer->id ?? null;
        if (is_null($recipientId)) {
            Log::error("Failed to send pickup cancellation notification: Organizer ID is null for pickup ID {$pickup->id}");
            return false;
        }

        $dataPayload = [
            'action' => 'Pickup Cancelled',
            'type' => 'pickup_cancelled',
            'pickup_id' => $pickup->id,
            'recycler_name' => $recycler->name,
            'reason' => $reason,
        ];

        $this->firebaseService->sendToUser($organizer->id, $title, $message, $dataPayload);

        Notification::create([
            'recipient_type' => 'App\\Models\\Organizer',
            'recipient_id' => $recipientId,
            'type' => 'pickup_cancelled',
            'title' => $title,
            'message' => $message,
            'data' => $dataPayload,
            'is_read' => false,
        ]);
    }

    public function sendPickupStatusUpdateNotification($pickup, $recycler, $newStatus, $oldStatus = null)
    {
        $title = $this->getPickupStatusTitle($newStatus);
        $message = $this->getPickupStatusMessage($newStatus, [
            'pickup_id' => $pickup->id,
            'organizer_name' => $pickup->organizer->name,
        ]);

        $recipientId = $recycler->id ?? null;
        if (is_null($recipientId)) {
            Log::error("Failed to send pickup status update notification: Recycler ID is null for pickup ID {$pickup->id}");
            return false;
        }

        $dataPayload = [
            'action' => 'Pickup Updated',
            'type' => Notification::TYPE_PICKUP_UPDATED,
            'pickup_id' => $pickup->id,
            'status' => $newStatus,
            'organizer_name' => $pickup->organizer->name,
        ];

        $this->firebaseService->sendToUser($recycler->id, $title, $message, $dataPayload);

        Notification::create([
            'recipient_type' => 'App\\Models\\User',
            'recipient_id' => $recipientId,
            'type' => Notification::TYPE_PICKUP_UPDATED,
            'title' => $title,
            'message' => $message,
            'data' => $dataPayload,
            'is_read' => false,
        ]);
    }

    private function getRelevantUsersForEvent($event)
    {
        if (!$event->organizer_id) {
            return [];
        }

        return Subscription::where('organizer_id', $event->organizer_id)
            ->with('user') // Eager load the user relationship
            ->get()
            ->pluck('user') // Extract the user models
            ->filter(); // Remove any null users
    }

    public function markAsRead(Notification $notification)
    {
        $notification->markAsRead();
        return $notification;
    }

    public function markAllAsRead(User $user)
    {
        // This method needs to be updated to use recipient_id and recipient_type
        return Notification::where('recipient_id', $user->id)
            ->where('recipient_type', 'App\\Models\\User')
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }

    public function getUserNotifications($userId, $limit = 20, $offset = 0)
    {
        return Notification::where('recipient_id', $userId)
            ->where('recipient_type', 'App\\Models\\User')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    public function getUnreadCount($userId)
    {
        return Notification::where('recipient_id', $userId)
            ->where('recipient_type', 'App\\Models\\User')
            ->where('is_read', false)
            ->count();
    }

    public function createEventNotification(User $user, string $eventTitle, string $action, array $data = [])
    {
        $title = $this->getEventNotificationTitle($eventTitle, $action);
        $message = $this->getEventNotificationMessage($eventTitle, $action, $data);

        $recipientId = $user->id ?? null;
        if (is_null($recipientId)) {
            Log::error("Failed to create event notification: User ID is null for event '{$eventTitle}'");
            return false;
        }

        // Send Firebase notification first to get the message ID
        $firebaseResult = $this->firebaseService->sendToUser(
            $user->id,
            $title,
            $message,
            array_merge($data, ['type' => Notification::TYPE_EVENT_NOTIFICATION])
        );

        $firebaseMessageId = null;
        if ($firebaseResult && isset($firebaseResult['results'][0]['message_id'])) {
            $firebaseMessageId = $firebaseResult['results'][0]['message_id'];
        }

        $notification = Notification::create([
            'recipient_type' => 'App\\Models\\User',
            'recipient_id' => $recipientId,
            'type' => Notification::TYPE_EVENT_NOTIFICATION,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
            'is_sent' => (bool) $firebaseMessageId,
            'sent_at' => $firebaseMessageId ? now() : null,
            'firebase_message_id' => $firebaseMessageId,
        ]);

        return $notification;
    }

    public function createPickupStatusNotification(User $user, string $status, array $data = [])
    {
        $title = $this->getPickupStatusTitle($status);
        $message = $this->getPickupStatusMessage($status, $data);

        $recipientId = $user->id ?? null;
        if (is_null($recipientId)) {
            Log::error("Failed to create pickup status notification: User ID is null for status '{$status}'");
            return false;
        }

        // Send Firebase notification first to get the message ID
        $firebaseResult = $this->firebaseService->sendToUser(
            $user->id,
            $title,
            $message,
            array_merge($data, ['type' => Notification::TYPE_PICKUP_UPDATED])
        );

        $firebaseMessageId = null;
        if ($firebaseResult && isset($firebaseResult['results'][0]['message_id'])) {
            $firebaseMessageId = $firebaseResult['results'][0]['message_id'];
        }

        $notification = Notification::create([
            'recipient_type' => 'App\\Models\\User',
            'recipient_id' => $recipientId,
            'type' => Notification::TYPE_PICKUP_UPDATED,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
            'is_sent' => (bool) $firebaseMessageId,
            'sent_at' => $firebaseMessageId ? now() : null,
            'firebase_message_id' => $firebaseMessageId,
        ]);

        return $notification;
    }

    public function createPickupRequestNotification(Organizer $organizer, array $data = [])
    {
        $title = 'New Pickup Request';
        $message = "New pickup request from {$data['user_name']}";

        $recipientId = $organizer->id ?? null;
        if (is_null($recipientId)) {
            Log::error("Failed to create pickup request notification: Organizer ID is null for pickup request.");
            return false;
        }

        // Send Firebase notification first to get the message ID
        $firebaseResult = $this->firebaseService->sendToUser(
            $organizer->id,
            $title,
            $message,
            array_merge($data, ['type' => Notification::TYPE_PICKUP_REQUESTED])
        );

        $firebaseMessageId = null;
        if ($firebaseResult && isset($firebaseResult['results'][0]['message_id'])) {
            $firebaseMessageId = $firebaseResult['results'][0]['message_id'];
        }

        $notification = Notification::create([
            'recipient_type' => 'App\\Models\\Organizer',
            'recipient_id' => $recipientId,
            'type' => Notification::TYPE_PICKUP_REQUESTED,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
            'is_sent' => (bool) $firebaseMessageId,
            'sent_at' => $firebaseMessageId ? now() : null,
            'firebase_message_id' => $firebaseMessageId,
        ]);

        return $notification;
    }

    private function getEventNotificationTitle(string $eventTitle, string $action): string
    {
        switch ($action) {
            case 'Event Created':
                return "New Event: {$eventTitle}";
            case 'Event Updated':
                return "Event Updated: {$eventTitle}";
            case 'Event Cancelled':
                return "Event Cancelled: {$eventTitle}";
            default:
                return "Event Notification: {$eventTitle}";
        }
    }

    private function getEventNotificationMessage(string $eventTitle, string $action, array $data): string
    {
        switch ($action) {
            case 'Event Created':
                return "A new event '{$eventTitle}' has been created. Check it out!";
            case 'Event Updated':
                return "The event '{$eventTitle}' has been updated. Check the latest details!";
            case 'Event Cancelled':
                $reason = $data['reason'] ?? '';
                return "The event '{$eventTitle}' has been cancelled." . ($reason ? " Reason: {$reason}" : '');
            default:
                return "Event '{$eventTitle}' notification";
        }
    }

    private function getPickupStatusTitle(string $status): string
    {
        switch ($status) {
            case 'In Progress':
                return 'Pickup In Progress';
            case 'Completed':
                return 'Pickup Completed';
            case 'Rejected':
                return 'Pickup Rejected';
            default:
                return 'Pickup Status Updated';
        }
    }

    private function getPickupStatusMessage(string $status, array $data): string
    {
        $organizerName = $data['organizer_name'] ?? 'The organizer';
        //$category = $data['category'] ?? 'your items';
        $pickupId = $data['pickup_id'] ?? 'N/A'; // Retrieve pickup_id from $data, with a fallback

        switch ($status) {
            case 'In Progress':
                return "{$organizerName} has started processing your pickup request for pickup ID {$pickupId}.";
            case 'Completed':
                return "Your pickup request for pickup ID {$pickupId} has been completed successfully.";
            case 'Rejected':
                return "{$organizerName} has rejected your pickup request for pickup ID {$pickupId}.";
            default:
                return "Your pickup request status has been updated to: {$status}";
        }
    }
}
