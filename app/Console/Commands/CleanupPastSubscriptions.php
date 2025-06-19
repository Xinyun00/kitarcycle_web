<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\SubscriptionController;

class CleanupPastSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:cleanup-past';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove subscriptions for past events and their calendar entries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new SubscriptionController(new \App\Services\CalendarService());
        $response = $controller->cleanupPastSubscriptions();

        $data = $response->getData(true);

        if ($data['success']) {
            $this->info($data['message']);
            $this->info('Removed ' . count($data['removed_calendar_events']) . ' calendar events.');
        } else {
            $this->error($data['message']);
        }
    }
}
