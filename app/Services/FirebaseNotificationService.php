<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class FirebaseNotificationService
{
    private $serverKey;
    private $fcmUrl = 'https://fcm.googleapis.com/fcm/send';

    public function __construct()
    {
        $this->serverKey = config('services.firebase.server_key');
    }

    /**
     * Send notification to a single user
     */
    public function sendToUser($userId, $title, $message, $data = [])
    {
        $user = User::find($userId);
        if (!$user) {
            Log::error("User not found: {$userId}");
            return false;
        }

        $tokens = UserFcmToken::active()->forUser($userId)->pluck('token')->toArray();

        if (empty($tokens)) {
            Log::warning("No FCM tokens found for user: {$userId}");
            return false;
        }

        return $this->sendToTokens($tokens, $title, $message, $data);
    }

    /**
     * Send notification to multiple users
     */
    public function sendToUsers($userIds, $title, $message, $data = [])
    {
        $tokens = UserFcmToken::active()
            ->whereIn('user_id', $userIds)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            Log::warning("No FCM tokens found for users: " . implode(', ', $userIds));
            return false;
        }

        return $this->sendToTokens($tokens, $title, $message, $data);
    }

    /**
     * Send notification to specific tokens
     */
    public function sendToTokens($tokens, $title, $message, $data = [])
    {
        if (empty($tokens)) {
            return false;
        }

        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $message,
                'sound' => 'default',
                'badge' => 1,
            ],
            'data' => $data,
            'priority' => 'high',
            'content_available' => true,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('Firebase notification sent successfully', [
                    'success' => $result['success'] ?? 0,
                    'failure' => $result['failure'] ?? 0,
                ]);

                // Handle invalid tokens
                $this->handleInvalidTokens($result, $tokens);

                return $result;
            } else {
                Log::error('Firebase notification failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return false;
            }
        } catch (Exception $e) {
            Log::error('Firebase notification exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle invalid tokens from Firebase response
     */
    private function handleInvalidTokens($result, $tokens)
    {
        if (isset($result['results'])) {
            foreach ($result['results'] as $index => $tokenResult) {
                if (isset($tokenResult['error'])) {
                    $error = $tokenResult['error'];
                    if (in_array($error, ['NotRegistered', 'InvalidRegistration'])) {
                        // Deactivate invalid token
                        UserFcmToken::where('token', $tokens[$index])
                            ->update(['is_active' => false]);

                        Log::info("Deactivated invalid FCM token: {$tokens[$index]}");
                    }
                }
            }
        }
    }

    /**
     * Send notification for event updates to subscribers
     */
    public function sendEventNotification($eventId, $type, $title, $message, $additionalData = [])
    {
        $subscriberIds = \App\Models\Subscription::where('event_id', $eventId)
            ->pluck('user_id')
            ->toArray();

        if (empty($subscriberIds)) {
            Log::info("No subscribers found for event: {$eventId}");
            return false;
        }

        $data = array_merge([
            'type' => $type,
            'event_id' => $eventId,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ], $additionalData);

        // Create notification records
        foreach ($subscriberIds as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);
        }

        return $this->sendToUsers($subscriberIds, $title, $message, $data);
    }

    /**
     * Send pickup notification to organizer
     */
    public function sendPickupNotificationToOrganizer($organizerId, $pickupId, $title, $message, $additionalData = [])
    {
        $data = array_merge([
            'type' => Notification::TYPE_PICKUP_REQUESTED,
            'pickup_id' => $pickupId,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ], $additionalData);

        // Create notification record
        Notification::create([
            'user_id' => $organizerId,
            'type' => Notification::TYPE_PICKUP_REQUESTED,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        return $this->sendToUser($organizerId, $title, $message, $data);
    }

    /**
     * Send pickup status update to recycler
     */
    public function sendPickupStatusUpdate($recyclerId, $pickupId, $status, $title, $message, $additionalData = [])
    {
        $data = array_merge([
            'type' => Notification::TYPE_PICKUP_UPDATED,
            'pickup_id' => $pickupId,
            'status' => $status,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ], $additionalData);

        // Create notification record
        Notification::create([
            'user_id' => $recyclerId,
            'type' => Notification::TYPE_PICKUP_UPDATED,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        return $this->sendToUser($recyclerId, $title, $message, $data);
    }
}
