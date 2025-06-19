<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\TierLevel;

class AssignGreenSeedTier extends Command
{
    protected $signature = 'users:assign-green-seed';
    protected $description = 'Assign Green Seed tier to all existing users';

    public function handle()
    {
        $greenSeedTier = TierLevel::where('name', 'Green Seed')->first();

        if (!$greenSeedTier) {
            $this->error('Green Seed tier not found! Please run the TierLevelSeeder first.');
            return 1;
        }

        $users = User::whereNull('tier_level_id')->orWhere('tier_level_id', '!=', $greenSeedTier->id)->get();

        $count = 0;
        foreach ($users as $user) {
            $user->tier_level_id = $greenSeedTier->id;
            $user->save();
            $count++;
        }

        $this->info("Successfully assigned Green Seed tier to {$count} users.");
        return 0;
    }
}
