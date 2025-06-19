<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\event;
use App\Services\CalendarService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    protected $calendarService;

    public function __construct(CalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Subscribe a user to an event
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'event_id' => 'required|integer|exists:events,id',
        ]);

        try {
            DB::beginTransaction();

            $event = Event::findOrFail($request->event_id);

            // Check if event is past
            if ($event->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot subscribe to past events.',
                ], 400);
            }

            // Check if already subscribed
            $existing = Subscription::where('user_id', $request->user_id)
                ->where('event_id', $request->event_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already subscribed to this event.',
                ], 200);
            }

            // Generate unique calendar event ID for mobile tracking
            $calendarEventId = Str::uuid();

            // Create subscription
            $subscription = Subscription::create([
                'user_id' => $request->user_id,
                'event_id' => $request->event_id,
                'calendar_event_id' => $calendarEventId,
            ]);

            // Load event relationship
            $subscription->load('event');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully subscribed to the event.',
                'data' => [
                    'subscription' => $subscription,
                    'calendar_data' => $this->calendarService->generateCalendarEventData($event),
                    'mobile_calendar' => $this->calendarService->formatForMobileCalendar($subscription),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to subscribe to event.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    //Unsubscribe
    public function unsubscribe(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'event_id' => 'required|integer|exists:events,id',
        ]);

        try {
            DB::beginTransaction();

            $subscription = Subscription::where('user_id', $request->user_id)
                ->where('event_id', $request->event_id)
                ->with('event')
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found.',
                ], 404);
            }

            $calendarEventId = $subscription->calendar_event_id;
            $subscription->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully unsubscribed from the event.',
                'calendar_event_id' => $calendarEventId, // For mobile to remove from calendar
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to unsubscribe from event.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all subscriptions for a user
     */
    public function userSubscriptions($userId)
    {
        try {
            $subscriptions = Subscription::where('user_id', $userId)
                ->with(['event' => function ($query) {
                    $query->select('id', 'eventTitle', 'eventDetails', 'location', 'startDate', 'endDate', 'image');
                }])
                ->get();

            $calendarEvents = $subscriptions->map(function ($subscription) {
                return $this->calendarService->formatForMobileCalendar($subscription);
            });

            return response()->json([
                'success' => true,
                'subscriptions' => $subscriptions,
                'calendar_events' => $calendarEvents,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscriptions.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clean up past event subscriptions (for scheduled task)
     */
    public function cleanupPastSubscriptions()
    {
        try {
            $pastSubscriptions = Subscription::whereHas('event', function ($query) {
                $query->where('endDate', '<', Carbon::now());
            })->with('event')->get();

            $removedCalendarEvents = [];

            foreach ($pastSubscriptions as $subscription) {
                $removedCalendarEvents[] = [
                    'calendar_event_id' => $subscription->calendar_event_id,
                    'event_title' => $subscription->event->eventTitle,
                ];
            }

            // Delete past subscriptions
            Subscription::whereHas('event', function ($query) {
                $query->where('endDate', '<', Carbon::now());
            })->delete();

            return response()->json([
                'success' => true,
                'message' => 'Past subscriptions cleaned up successfully.',
                'removed_calendar_events' => $removedCalendarEvents,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup past subscriptions.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription status for an event
     */
    public function getSubscriptionStatus($userId, $eventId)
    {
        try {
            $event = Event::findOrFail($eventId);
            $subscription = Subscription::where('user_id', $userId)
                ->where('event_id', $eventId)
                ->first();

            return response()->json([
                'success' => true,
                'is_subscribed' => !is_null($subscription),
                'can_subscribe' => !$event->isPast(),
                'event_status' => $event->getStatus(),
                'subscription_data' => $subscription ? $this->calendarService->formatForMobileCalendar($subscription) : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get subscription status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
