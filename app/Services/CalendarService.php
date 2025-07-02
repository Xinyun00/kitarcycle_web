<?php

namespace App\Services;

use Carbon\Carbon;

class CalendarService
{
    /**
     * Generate calendar event data for mobile integration
     */
    public function generateCalendarEventData($event)
    {
        return [
            'title' => $event->eventTitle,
            'description' => $event->eventDetails,
            'location' => $event->location,
            'startDate' => Carbon::parse($event->startDate)->toISOString(),
            'endDate' => Carbon::parse($event->endDate)->toISOString(),
            'allDay' => $this->isAllDayEvent($event),
            'reminder' => [
                ['minutes' => 60], // 1 hour before
                ['minutes' => 1440], // 1 day before
            ],
            'recurrence' => null, // Add if event repeats
        ];
    }

    /**
     * Check if event is all day
     */
    private function isAllDayEvent($event)
    {
        $start = Carbon::parse($event->startDate);
        $end = Carbon::parse($event->endDate);

        return $start->format('H:i:s') === '00:00:00' &&
               $end->format('H:i:s') === '23:59:59';
    }

    /**
     * Generate calendar event for mobile response
     */
    public function formatForMobileCalendar($subscription)
    {
        $event = $subscription->event;

        return [
            'id' => $subscription->id,
            'calendar_event_id' => $subscription->calendar_event_id,
            'event_id' => $event->id,
            'title' => $event->eventTitle,
            'start' => Carbon::parse($event->startDate)->toISOString(),
            'end' => Carbon::parse($event->endDate)->toISOString(),
            'description' => $event->eventDetails,
            'location' => $event->location,
            'color' => $event->isPast() ? '#999999' : '#007bff', // Gray for past events
            'is_past' => $event->isPast(),
            'status' => $event->getStatus(),
        ];
    }

    /**
     * Generate iCal format for calendar export
     */
    public function generateICalData($event)
    {
        $startDate = Carbon::parse($event->startDate);
        $endDate = Carbon::parse($event->endDate);

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//KitarCycle//Event Calendar//EN\r\n";
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . md5($event->id . $event->eventTitle) . "@kitarcycle.com\r\n";
        $ical .= "DTSTART:" . $startDate->utc()->format('Ymd\THis\Z') . "\r\n";
        $ical .= "DTEND:" . $endDate->utc()->format('Ymd\THis\Z') . "\r\n";
        $ical .= "SUMMARY:" . $this->escapeString($event->eventTitle) . "\r\n";
        $ical .= "DESCRIPTION:" . $this->escapeString($event->eventDetails) . "\r\n";
        $ical .= "LOCATION:" . $this->escapeString($event->location) . "\r\n";
        $ical .= "STATUS:CONFIRMED\r\n";
        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";

        return $ical;
    }

    /**
     * Escape string for iCal format
     */
    private function escapeString($string)
    {
        return str_replace([',', ';', '\\', "\n", "\r"], ['\\,', '\\;', '\\\\', '\\n', '\\r'], $string);
    }

    /**
     * Check if event conflicts with existing subscriptions
     */
    public function checkEventConflicts($userId, $newEvent)
    {
        $newStart = Carbon::parse($newEvent->startDate);
        $newEnd = Carbon::parse($newEvent->endDate);

        // Get user's existing subscriptions
        $existingSubscriptions = \App\Models\Subscription::where('user_id', $userId)
            ->with('event')
            ->get();

        $conflicts = [];

        foreach ($existingSubscriptions as $subscription) {
            $existingStart = Carbon::parse($subscription->event->startDate);
            $existingEnd = Carbon::parse($subscription->event->endDate);

            // Check for overlap
            if ($newStart->lt($existingEnd) && $newEnd->gt($existingStart)) {
                $conflicts[] = [
                    'event_id' => $subscription->event->id,
                    'event_title' => $subscription->event->eventTitle,
                    'start' => $existingStart->toISOString(),
                    'end' => $existingEnd->toISOString(),
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Get calendar events for a date range
     */
    public function getCalendarEventsForRange($userId, $startDate, $endDate)
    {
        $subscriptions = \App\Models\Subscription::where('user_id', $userId)
            ->whereHas('event', function($query) use ($startDate, $endDate) {
                $query->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('startDate', [$startDate, $endDate])
                      ->orWhereBetween('endDate', [$startDate, $endDate])
                      ->orWhere(function($q2) use ($startDate, $endDate) {
                          $q2->where('startDate', '<=', $startDate)
                             ->where('endDate', '>=', $endDate);
                      });
                });
            })
            ->with('event')
            ->get();

        return $subscriptions->map(function($subscription) {
            return $this->formatForMobileCalendar($subscription);
        });
    }
}
