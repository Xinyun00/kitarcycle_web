<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\NotificationService; // Assuming you have a NotificationService for handling notifications

class EventController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of events.
     */
    public function index()
    {
        try {
            $events = Event::with('organizer')->where('status', 'active')->get();
            return response()->json($events, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching events: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch events'], 500);
        }
    }

    /**
     * Store a newly created event in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'eventTitle' => 'required|string',
            'image' => 'required|image|mimes:jpg,jpeg,png', // Validating as image file
            'eventDetails' => 'required|string',
            'location' => 'required|string',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
            'organizer_id' => 'required|exists:organizers,id'
        ]);

        $imagePath = null;
        try {
            DB::beginTransaction();

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('kitarcycle/events', 'public');
            }

            // Create the event
            $event = Event::create([
                'eventTitle' => $request->eventTitle,
                'image' => $imagePath, // Store the path returned by the store() method
                'eventDetails' => $request->eventDetails,
                'location' => $request->location,
                'startDate' => $request->startDate,
                'endDate' => $request->endDate,
                'organizer_id' => $request->organizer_id
            ]);

            DB::commit();
            return response()->json(['message' => 'Event created successfully!', 'event' => $event], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating event: ' . $e->getMessage());

            // CHANGE: If an image was saved, delete it using Storage facade on failure
            if ($imagePath) {
                if (Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
            }

            return response()->json(['message' => 'Failed to create event'], 500);
        }
    }

    /**
     * Display a listing of events by a specific organizer.
     */
    public function getEventsByOrganizer($organizerId)
    {
        try {
            $events = Event::where('organizer_id', $organizerId)
                ->where('status', 'active')
                ->get();

            if ($events->isEmpty()) {
                return response()->json(['message' => 'No active events found for this organizer'], 404);
            }

            return response()->json($events, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching events for organizer: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch events'], 500);
        }
    }

    /**
     * Display the specified event.
     */
    public function show($id)
    {
        try {
            $event = Event::findOrFail($id);
            return response()->json($event, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching event: ' . $e->getMessage());
            return response()->json(['message' => 'Event not found'], 404);
        }
    }

    /**
     * Update the specified event in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'eventTitle' => 'sometimes|string',
            'image' => 'sometimes|file|mimes:jpeg,png,jpg',
            'eventDetails' => 'sometimes|string',
            'location' => 'sometimes|string',
            'startDate' => 'sometimes|date',
            'endDate' => 'sometimes|date|after_or_equal:startDate',
            'organizer_id' => 'sometimes|exists:organizers,id'
        ]);

        $newImagePath = null;
        try {
            DB::beginTransaction();

            $event = Event::findOrFail($id);
            $eventData = $request->only(['eventTitle', 'eventDetails', 'location', 'startDate', 'endDate', 'organizer_id']);

            // CHANGE: Switched to store() and Storage::delete() for updates
            if ($request->hasFile('image')) {
                // Delete old image if it exists
                if ($event->image) {
                    if (Storage::disk('public')->exists($event->image)) {
                        Storage::disk('public')->delete($event->image);
                    }
                }
                // Store the new image and get its path
                $newImagePath = $request->file('image')->store('kitarcycle/events', 'public');
                $eventData['image'] = $newImagePath;
            }

            // Update the event with the new data
            $event->update($eventData);

            DB::commit();

            return response()->json(['message' => 'Event updated successfully!', 'event' => $event], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            // CHANGE: If a new image was uploaded during a failed update, delete it
            if ($newImagePath) {
                if (Storage::disk('public')->exists($newImagePath)) {
                    Storage::disk('public')->delete($newImagePath);
                }
            }

            Log::error('Error updating event: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update event'], 500);
        }
    }

    /**
     * Cancel the specified event
     */
    public function cancelEvent(Request $request, $id)
    {
        $request->validate([
            'cancellation_reason' => 'required|string|max:255',
        ]);

        $event = Event::find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Event not found.'], 404);
        }

        try {
            DB::beginTransaction();

            $reason = $request->input('cancellation_reason');

            // Update status and reason instead of deleting
            $event->status = 'cancelled';
            $event->cancellation_reason = $reason;
            $event->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving event cancellation ' . $id . ': ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update the event in the database.'], 500);
        }

        // **FIX**: Handle notification failure separately
        try {
            $this->notificationService->sendEventCancelledNotification($event, $reason);
        } catch (\Exception $e) {
            // Log the notification error but don't fail the entire request
            Log::error('Failed to send cancellation notification for event ' . $id . ': ' . $e->getMessage());
        }

        // Return a consistent success response
        return response()->json(['success' => true, 'message' => 'Event cancelled successfully!'], 200);
    }


    /**
     * Remove the specified event from storage.
     */
    public function delete($id)
    {
        return response()->json([
            'message' => 'This action is deprecated. Please use the cancel event functionality.'
        ], 400);
    }
}
