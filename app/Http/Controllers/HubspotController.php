<?php

namespace App\Http\Controllers;

use App\Models\HubspotRetrievalHistory;
use App\Models\HubspotContact;
use App\Models\HubspotSyncStatus;
use App\Models\HubspotContactBuffer;
use App\Services\HubspotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class HubspotController extends Controller
{
    protected $hubspotService;
    protected $batchSize = 1000; // Number of contacts per batch

    public function __construct(HubspotService $hubspotService)
    {
        $this->hubspotService = $hubspotService;
    }

    /**
     * Display dashboard view with manual sync button
     */
    public function dashboard()
    {
        $syncStatus = $this->hubspotService->getSyncStatus('contacts');
        $totalContacts = HubspotContact::count();
        $lastSyncDate = $syncStatus->last_successful_sync;
        $bufferCount = HubspotContactBuffer::count();

        // Get next start date (which is the last end date)
        $nextStartDate = $syncStatus->last_sync_timestamp ?? '2020-03-01T00:00:00Z';
        $endDate = Carbon::now()->format('Y-m-d\TH:i:s\Z');

        return view('hubspot.dashboard', compact(
            'syncStatus',
            'totalContacts',
            'lastSyncDate',
            'bufferCount',
            'nextStartDate',
            'endDate'
        ));
    }

    /**
     * Display sync history view
     */
    public function syncHistory()
    {
        $syncStatus = $this->hubspotService->getSyncStatus('contacts');
        $totalContacts = HubspotContact::count();
        $recentContacts = HubspotContact::latest()->take(10)->get();
        $bufferCount = HubspotContactBuffer::count();

        return view('hubspot.sync-history', compact(
            'syncStatus',
            'totalContacts',
            'recentContacts',
            'bufferCount'
        ));
    }

    /**
     * Trigger manual sync process with a single batch
     */
    public function startSync(Request $request)
    {
        // Validate input
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $syncStatus = $this->hubspotService->getSyncStatus('contacts');

        // Already running? Don't start another
        if ($syncStatus->status === 'running') {
            return redirect()->back()->with('warning', 'Sync is already in progress');
        }

        // Determine date range
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->format('Y-m-d\TH:i:s\Z')
            : ($syncStatus->last_sync_timestamp
                ? $syncStatus->last_sync_timestamp->format('Y-m-d\TH:i:s\Z')
                : '2020-03-01T00:00:00Z');

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->format('Y-m-d\TH:i:s\Z')
            : Carbon::now()->format('Y-m-d\TH:i:s\Z');

        // Update status to running
        $this->hubspotService->updateSyncStatus('contacts', [
            'status' => 'running',
            'start_window' => $startDate,
            'end_window' => $endDate,
            'total_synced' => 0,
            'total_errors' => 0,
            'error_log' => null
        ]);

        // Start sync process for one batch
        $this->processSingleBatch($startDate, $endDate);

        return redirect()->route('hubspot.dashboard')
            ->with('success', 'Contact sync batch has been processed');
    }

    /**
     * Process a single batch of contacts
     */
    public function processSingleBatch($startDate, $endDate)
    {
        $syncStatus = $this->hubspotService->getSyncStatus('contacts');
        $errors = [];

        try {
            // Find optimal time window for this batch
            $result = $this->hubspotService->findOptimalTimeWindow(
                $startDate,
                $endDate
            );

            $optimalEndDate = $result['endDate'];
            $totalContacts = $result['totalContacts'];

            // If no contacts found, update status and return
            if ($totalContacts == 0) {
                $this->hubspotService->updateSyncStatus('contacts', [
                    'status' => 'completed',
                    'last_sync_timestamp' => $endDate,
                    'last_successful_sync' => Carbon::now(),
                ]);

                Log::info("No contacts found in window", [
                    'startDate' => $startDate,
                    'endDate' => $endDate
                ]);

                return;
            }

            Log::info("Processing batch", [
                'startDate' => $startDate,
                'endDate' => $optimalEndDate,
                'expectedCount' => $totalContacts
            ]);

            // Fetch contacts in this time window
            $contacts = $this->hubspotService->getAllContactsInTimeWindow(
                $startDate,
                $optimalEndDate
            );

            $actualCount = count($contacts);
            Log::info("Retrieved {$actualCount} contacts for processing");

            // Save retrieval history
            HubspotRetrievalHistory::create([
                'retrieved_count' => $actualCount,
                'start_date' => $startDate,
                'end_date' => $optimalEndDate
            ]);

            // Process contacts in chunks of 1000
            $this->processContacts($contacts);

            // Update sync status
            $this->hubspotService->updateSyncStatus('contacts', [
                'status' => 'waiting',
                'last_sync_timestamp' => $optimalEndDate,
                'total_synced' => $syncStatus->total_synced + $actualCount,
                'last_successful_sync' => Carbon::now(),
            ]);

            Log::info("Batch completed successfully", [
                'contactsProcessed' => $actualCount,
                'nextStartDate' => $optimalEndDate
            ]);
        } catch (\Exception $e) {
            Log::error("HubSpot sync error", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $errors[] = $e->getMessage();

            // Update sync status with error
            $this->hubspotService->updateSyncStatus('contacts', [
                'status' => 'failed',
                'total_errors' => $syncStatus->total_errors + 1,
                'error_log' => json_encode($errors)
            ]);
        }
    }

    /**
     * Process contacts and insert directly to database
     */
    private function processContacts($contacts)
    {
        // Process in chunks of 1000 for efficiency
        $chunks = array_chunk($contacts, 1000);

        foreach ($chunks as $chunk) {
            $records = [];

            foreach ($chunk as $contact) {
                $records[] = [
                    'hubspot_id' => $contact['id'],
                    'email' => $contact['properties']['email'] ?? null,
                    'firstname' => $contact['properties']['firstname'] ?? null,
                    'lastname' => $contact['properties']['lastname'] ?? null,
                    'gender' => $contact['properties']['gender'] ?? null,
                    'hubspot_created_at' => isset($contact['properties']['createdate'])
                        ? Carbon::parse($contact['properties']['createdate'])
                        : null,
                    'hubspot_updated_at' => isset($contact['properties']['lastmodifieddate'])
                        ? Carbon::parse($contact['properties']['lastmodifieddate'])
                        : null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }

            // Use upsert to handle duplicates
            DB::table('hubspot_contacts')->upsert(
                $records,
                ['hubspot_id'],
                ['email', 'firstname', 'lastname', 'gender', 'hubspot_updated_at', 'updated_at']
            );

            Log::info("Inserted batch of " . count($records) . " contacts");
        }
    }

    /**
     * Process contacts with buffer mechanism
     */
    private function processContactsWithBuffer($contacts)
    {
        // Get contacts from buffer first
        $bufferedContacts = HubspotContactBuffer::all();

        // Merge buffer with new contacts
        $allContacts = $bufferedContacts->toArray();
        foreach ($contacts as $contact) {
            $allContacts[] = [
                'hubspot_id' => $contact['id'],
                'data' => json_encode($contact)
            ];
        }

        // Empty the buffer table
        HubspotContactBuffer::truncate();

        // Process in chunks of batchSize (1000)
        $chunks = array_chunk($allContacts, $this->batchSize);

        // Last chunk is partial (less than batchSize) - put it back in buffer
        if (count($chunks) > 0 && count($chunks[count($chunks) - 1]) < $this->batchSize) {
            $lastChunk = array_pop($chunks);

            // Store partial chunk in buffer for next run
            foreach ($lastChunk as $contact) {
                HubspotContactBuffer::create([
                    'hubspot_id' => $contact['hubspot_id'],
                    'data' => $contact['data']
                ]);
            }

            Log::info("Stored " . count($lastChunk) . " contacts in buffer for next run");
        }

        // Process all full chunks
        foreach ($chunks as $chunk) {
            $records = [];

            foreach ($chunk as $contact) {
                $contactData = is_string($contact['data']) ? json_decode($contact['data'], true) : $contact['data'];

                $records[] = [
                    'hubspot_id' => $contact['hubspot_id'],
                    'email' => $contactData['properties']['email'] ?? null,
                    'firstname' => $contactData['properties']['firstname'] ?? null,
                    'lastname' => $contactData['properties']['lastname'] ?? null,
                    'gender' => $contactData['properties']['gender'] ?? null,
                    'hubspot_created_at' => isset($contactData['properties']['createdate'])
                        ? Carbon::parse($contactData['properties']['createdate'])
                        : null,
                    'hubspot_updated_at' => isset($contactData['properties']['lastmodifieddate'])
                        ? Carbon::parse($contactData['properties']['lastmodifieddate'])
                        : null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }

            // Use upsert to handle duplicates
            DB::table('hubspot_contacts')->upsert(
                $records,
                ['hubspot_id'],
                ['email', 'firstname', 'lastname', 'gender', 'hubspot_updated_at', 'updated_at']
            );

            Log::info("Inserted batch of " . count($records) . " contacts");
        }
    }

    /**
     * API endpoint to check sync status
     */
    public function checkStatus()
    {
        $syncStatus = $this->hubspotService->getSyncStatus('contacts');
        $bufferCount = HubspotContactBuffer::count();

        return response()->json([
            'status' => $syncStatus->status,
            'last_sync' => $syncStatus->last_sync_timestamp,
            'total_synced' => $syncStatus->total_synced,
            'buffer_count' => $bufferCount
        ]);
    }

    /**
     * Cancel an ongoing sync
     */
    public function cancelSync()
    {
        $this->hubspotService->updateSyncStatus('contacts', [
            'status' => 'cancelled'
        ]);

        return redirect()->route('hubspot.dashboard')
            ->with('info', 'Sync has been cancelled');
    }

    public function retrievalHistory()
    {
        $retrievals = HubspotRetrievalHistory::orderBy('created_at', 'desc')
            ->paginate(20);

        return view('hubspot.retrieval-history', compact('retrievals'));
    }
}
