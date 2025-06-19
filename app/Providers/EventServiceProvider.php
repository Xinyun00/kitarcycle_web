<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Models\Event as EventModel;
use App\Observers\EventObserver;
use App\Events\EventCreated;
use App\Events\EventUpdated;
use App\Events\EventCancelled;
use App\Events\PickupRequested;
use App\Events\PickupStatusUpdated;
use App\Listeners\EventNotificationListener;
use App\Listeners\PickupNotificationListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        EventCreated::class => [
            EventNotificationListener::class . '@handleEventCreated',
        ],
        EventUpdated::class => [
            EventNotificationListener::class . '@handleEventUpdated',
        ],
        EventCancelled::class => [
            EventNotificationListener::class . '@handleEventCancelled',
        ],
        PickupRequested::class => [
            PickupNotificationListener::class . '@handlePickupRequested',
        ],
        PickupStatusUpdated::class => [
            PickupNotificationListener::class . '@handlePickupStatusUpdated',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Register the Event model observer
        EventModel::observe(EventObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
