<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Event;
use App\Services\CalendarService;
use App\Observers\EventObserver;
use App\Services\FirebaseNotificationService;
use App\Services\NotificationService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register CalendarService as a singleton
        $this->app->singleton(CalendarService::class, function ($app) {
            return new CalendarService();
        });

        $this->app->singleton(FirebaseNotificationService::class, function ($app) {
            return new FirebaseNotificationService();
        });

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService($app->make(FirebaseNotificationService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //Register Event Observer
        Event::observe(EventObserver::class);
    }
}
