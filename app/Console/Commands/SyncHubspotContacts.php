<?php
// app/Console/Commands/SyncHubspotContacts.php

namespace App\Console\Commands;

use App\Http\Controllers\HubspotController;
use App\Services\HubspotService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncHubspotContacts extends Command
{
    protected $signature = 'hubspot:sync-contacts 
                           {--start-date= : Start date in format YYYY-MM-DD}
                           {--end-date= : End date in format YYYY-MM-DD}
                           {--chunk-days=7 : Days per chunk for very large datasets}';

    protected $description = 'Sync contacts from HubSpot to database';

    private $hubspotService;
    private $hubspotController;

    public function __construct(HubspotService $hubspotService, HubspotController $hubspotController)
    {
        parent::__construct();
        $this->hubspotService = $hubspotService;
        $this->hubspotController = $hubspotController;
    }

    public function handle()
    {
        // Increase execution time
        set_time_limit(3600); // 1 hour
        
        $this->info('Starting HubSpot contacts sync...');
        
        $syncStatus = $this->hubspotService->getSyncStatus('contacts');
        
        // Determine date range
        $startDate = $this->option('start-date') 
            ? Carbon::parse($this->option('start-date'))->startOfDay()->format('Y-m-d\TH:i:s\Z')
            : ($syncStatus->last_sync_timestamp 
                ? $syncStatus->last_sync_timestamp->format('Y-m-d\TH:i:s\Z')
                : '2020-03-01T00:00:00Z');
        
        $endDate = $this->option('end-date')
            ? Carbon::parse($this->option('end-date'))->endOfDay()->format('Y-m-d\TH:i:s\Z')
            : Carbon::now()->format('Y-m-d\TH:i:s\Z');
        
        $this->info("Date range: $startDate to $endDate");
        
        // For very large datasets, process in chunks by days
        $startDateObj = Carbon::parse($startDate);
        $endDateObj = Carbon::parse($endDate);
        $chunkDays = (int)$this->option('chunk-days');
        
        if ($startDateObj->diffInDays($endDateObj) > $chunkDays) {
            $this->info("Processing large dataset in chunks of $chunkDays days");
            
            $currentChunkStart = $startDateObj->copy();
            $totalProcessed = 0;
            
            while ($currentChunkStart->lt($endDateObj)) {
                $chunkEnd = $currentChunkStart->copy()->addDays($chunkDays);
                
                // Don't exceed end date
                if ($chunkEnd->gt($endDateObj)) {
                    $chunkEnd = $endDateObj->copy();
                }
                
                $chunkStartStr = $currentChunkStart->format('Y-m-d\TH:i:s\Z');
                $chunkEndStr = $chunkEnd->format('Y-m-d\TH:i:s\Z');
                
                $this->info("Processing chunk: $chunkStartStr to $chunkEndStr");
                
                // Update status to running
                $this->hubspotService->updateSyncStatus('contacts', [
                    'status' => 'running',
                    'start_window' => $chunkStartStr,
                    'end_window' => $chunkEndStr,
                ]);
                
                // Run sync for this chunk
                try {
                    $this->hubspotController->syncContacts($chunkStartStr, $chunkEndStr);
                    $totalProcessed += $syncStatus->refresh()->total_synced;
                    $this->info("Chunk completed, processed {$syncStatus->total_synced} contacts");
                } catch (\Exception $e) {
                    $this->error("Error processing chunk: " . $e->getMessage());
                    // Continue to next chunk
                }
                
                // Move to next chunk
                $currentChunkStart = $chunkEnd->addSecond();
            }
            
            $this->info("Total contacts processed: $totalProcessed");
            
        } else {
            // Process entire range at once
            $this->info("Processing entire date range at once");
            
            // Update status to running
            $this->hubspotService->updateSyncStatus('contacts', [
                'status' => 'running',
                'start_window' => $startDate,
                'end_window' => $endDate,
                'total_synced' => 0,
                'total_errors' => 0,
                'error_log' => null
            ]);
            
            // Run sync
            try {
                $this->hubspotController->syncContacts($startDate, $endDate);
                $this->info("Sync completed, processed {$syncStatus->refresh()->total_synced} contacts");
            } catch (\Exception $e) {
                $this->error("Error during sync: " . $e->getMessage());
            }
        }
        
        $this->info('HubSpot contacts sync completed!');
        
        return Command::SUCCESS;
    }
}