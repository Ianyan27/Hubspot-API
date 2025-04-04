<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Services\HubspotService;
use App\Http\Controllers\HubspotController;
use Illuminate\Support\Carbon;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Schedule daily HubSpot contact sync
        $schedule->call(function () {
            $hubspotService = app(HubspotService::class);
            $hubspotController = app(HubspotController::class);
            
            $syncStatus = $hubspotService->getSyncStatus('contacts');
            
            // Only run if not already running
            if ($syncStatus->status !== 'running') {
                // Start from last sync or use default if first run
                $startDate = $syncStatus->last_sync_timestamp 
                    ? $syncStatus->last_sync_timestamp->format('Y-m-d\TH:i:s\Z')
                    : '2020-03-01T00:00:00Z';
                
                $endDate = Carbon::now()->format('Y-m-d\TH:i:s\Z');
                
                // Start the sync
                $hubspotController->syncContacts($startDate, $endDate);
            }
        })->dailyAt('01:00'); // Run at 1 AM
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}