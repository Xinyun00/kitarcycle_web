<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFcmToken;
use App\Models\Notification;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use App\Services\FirebaseNotificationService;
use App\Models\User;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

class NotificationController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected $notificationService;
    protected $firebaseService;

    public function __construct(NotificationService $notificationService, FirebaseNotificationService $firebaseService)
    {
        $this->notificationService = $notificationService;
        $this->firebaseService = $firebaseService;
        $this->middleware('auth:api,api_organizer');
    }

    /**
     * Store/Update FCM token for the authenticated user
     */
    public function storeFcmToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'device_type' => 'nullable|string|in:android,ios,web',
            'device_id' => 'nullable|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the authenticated user/organizer instance
            $notifiable = $request->user();

            if (!$notifiable) {
                // This case should ideally not be reached if middleware works
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            // Check if token already exists for this notifiable entity
            $existingToken = UserFcmToken::where('notifiable_id', $notifiable->id)
                ->where('notifiable_type', get_class($notifiable)) // Crucial for polymorphism
                ->where('token', $request->token)
                ->first();

            if ($existingToken) {
                // Update existing token
                $existingToken->update([
                    'device_type' => $request->device_type,
                    'device_id' => $request->device_id,
                    'device_name' => $request->device_name,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
            } else {
                // Deactivate old tokens for the same device and same notifiable (if device_id provided)
                if ($request->device_id) {
                    UserFcmToken::where('notifiable_id', $notifiable->id)
                        ->where('notifiable_type', get_class($notifiable)) // Crucial for polymorphism
                        ->where('device_id', $request->device_id)
                        ->update(['is_active' => false]);
                }

                // Create new token
                UserFcmToken::create([
                    'notifiable_id' => $notifiable->id,
                    'notifiable_type' => get_class($notifiable), // Store the class name (e.g., 'App\Models\User' or 'App\Models\Organizer')
                    'token' => $request->token,
                    'device_type' => $request->device_type,
                    'device_id' => $request->device_id,
                    'device_name' => $request->device_name,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'FCM token stored successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store FCM token: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to store FCM token',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get notifications for the authenticated user
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
            'type' => 'nullable|string',
            'read_status' => 'nullable|in:read,unread,all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $notifiable = $request->user(); // Authenticated User or Organizer

            if (!$notifiable) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $limit = $request->get('limit', 20);
            $offset = $request->get('offset', 0);
            $type = $request->get('type');
            $readStatus = $request->get('read_status', 'all');

            // Use the polymorphic relationship for notifications
            $query = Notification::where('recipient_id', $notifiable->id)
                ->where('recipient_type', get_class($notifiable)); // Filter by both ID and Type

            if ($type) {
                $query->where('type', $type);
            }

            if ($readStatus === 'read') {
                $query->where('is_read', true);
            } elseif ($readStatus === 'unread') {
                $query->where('is_read', false);
            }

            $notifications = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            $unreadCount = Notification::where('recipient_id', $notifiable->id)
                ->where('recipient_type', get_class($notifiable)) // Filter by both ID and Type
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => $notifications,
                    'unread_count' => $unreadCount,
                    'has_more' => $notifications->count() === $limit,
                    'total_count' => $query->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, $notificationId): JsonResponse
    {
        try {
            $user = Auth::user();
            $notification = Notification::where('recipient_id', $user->id)
                ->where('recipient_type', 'App\\Models\\User')
                ->where('id', $notificationId)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark multiple notifications as read
     */
    public function markMultipleAsRead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_ids' => 'required|array|min:1',
            'notification_ids.*' => 'required|integer|exists:notifications,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $notificationIds = $request->notification_ids;

            $count = Notification::where('user_id', $user->id)
                ->whereIn('id', $notificationIds)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => "Marked {$count} notifications as read"
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark multiple notifications as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notifications as read',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for the authenticated user
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            // Get the authenticated entity (can be User or Organizer)
            $notifiable = $request->user();

            if (!$notifiable) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            // Use the polymorphic relationship to update notifications
            // This is the key change that makes it work for both Users and Organizers
            $count = Notification::where('recipient_id', $notifiable->id)
                ->where('recipient_type', get_class($notifiable))
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => "Marked {$count} notifications as read"
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $count = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $count
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get unread count: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete a notification
     */
    public function deleteNotification(Request $request, $notificationId): JsonResponse
    {
        try {
            $user = Auth::user();
            $notification = Notification::where('user_id', $user->id)
                ->where('id', $notificationId)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete multiple notifications
     */
    public function deleteMultipleNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_ids' => 'required|array|min:1',
            'notification_ids.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $notificationIds = $request->notification_ids;

            $count = Notification::where('user_id', $user->id)
                ->whereIn('id', $notificationIds)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "Deleted {$count} notifications"
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete multiple notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Clear all notifications for the authenticated user
     */
    public function clearAllNotifications(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $count = Notification::where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'message' => "Cleared {$count} notifications"
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear all notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get notification preferences for the authenticated user
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $preferences = $user->notification_preferences ?? [
                'email_notifications' => true,
                'push_notifications' => true,
                'sms_notifications' => false,
                'notification_types' => [
                    Notification::TYPE_EVENT_NOTIFICATION,
                    Notification::TYPE_PICKUP_REQUESTED,
                    Notification::TYPE_PICKUP_UPDATED,
                ],
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '08:00',
                'timezone' => 'UTC',
            ];

            return response()->json([
                'success' => true,
                'data' => $preferences
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get notification preferences: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get notification preferences',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update notification preferences
     */
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_notifications' => 'nullable|boolean',
            'push_notifications' => 'nullable|boolean',
            'sms_notifications' => 'nullable|boolean',
            'notification_types' => 'nullable|array',
            'notification_types.*' => 'string',
            'quiet_hours_start' => 'nullable|date_format:H:i',
            'quiet_hours_end' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $currentPreferences = $user->notification_preferences ?? [];
            $newPreferences = array_merge($currentPreferences, $request->all());

            DB::table('users')
                ->where('id', $user->id)
                ->update(['notification_preferences' => json_encode($newPreferences)]);

            return response()->json([
                'success' => true,
                'message' => 'Notification preferences updated successfully',
                'data' => $newPreferences
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update notification preferences: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification preferences',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove FCM token (on logout)
     */
    public function removeFcmToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();

            $deleted = UserFcmToken::where('user_id', $user->id)
                ->where('token', $request->token)
                ->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'FCM token removed successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'FCM token not found'
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('Failed to remove FCM token: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove FCM token',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove all FCM tokens for the authenticated user
     */
    public function removeAllFcmTokens(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $count = UserFcmToken::where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'message' => "Removed {$count} FCM tokens"
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove all FCM tokens: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove FCM tokens',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user's registered devices
     */
    public function getRegisteredDevices(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $devices = UserFcmToken::where('user_id', $user->id)
                ->where('is_active', true)
                ->select(['id', 'device_type', 'device_name', 'device_id', 'last_used_at', 'created_at'])
                ->orderBy('last_used_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $devices
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get registered devices: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get registered devices',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Send a test notification to the authenticated user
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $result = $this->firebaseService->sendToUser(
                $user->id,
                'Test Notification',
                'This is a test notification to verify your settings.',
                [
                    'type' => Notification::TYPE_GENERAL,
                    'test' => true
                ]
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Test notification sent successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send test notification'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send test notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test notification',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
