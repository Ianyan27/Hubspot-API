<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\HubspotSyncStatus;

class HubspotService
{
    protected $apiKey;
    protected $baseUrl = 'https://api.hubapi.com';

    public function __construct()
    {
        $this->apiKey = config('services.hubspot.api_key');

        // Log API key configuration for debugging (without showing the actual key)
        if (empty($this->apiKey)) {
            Log::warning('HubSpot API key is not configured');
        } else {
            Log::info('HubSpot API key is configured');
        }
    }

    /**
     * Search contacts with specified filters
     */
    public function searchContacts($startDate, $endDate, $limit = 100, $after = null)
    {
        // Always use Bearer token authentication since that's what works in your environment
        $url = "{$this->baseUrl}/crm/v3/objects/contacts/search";

        $payload = [
            'filterGroups' => [
                [
                    'filters' => [
                        [
                            'propertyName' => 'createdate',
                            'operator' => 'GT',
                            'value' => $startDate
                        ],
                        [
                            'propertyName' => 'createdate',
                            'operator' => 'LT',
                            'value' => $endDate
                        ]
                    ]
                ]
            ],
            'properties' => ['firstname', 'lastname', 'gender', 'email', 'createdate', 'lastmodifieddate'],
            'limit' => $limit,
        ];

        if ($after) {
            $payload['after'] = $after;
        }

        // Set a reasonable timeout to prevent hanging
        $response = Http::timeout(15)->withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        if ($response->failed()) {
            Log::error('HubSpot API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('HubSpot API error: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Count total contacts between date range
     */
    public function countContacts($startDate, $endDate)
    {
        $result = $this->searchContacts($startDate, $endDate, 1);
        return $result['total'] ?? 0;
    }

    /**
     * Adaptive time window algorithm to find optimal batch size
     * Returns the adjusted end date and total count
     */
    public function findOptimalTimeWindow($startDate, $endDate)
    {
        $targetBatchSize = 3000;  // Aim for 3000 contacts - safe for pagination and faster processing
        $optimalEndDate = $endDate;
        
        // Set script timeout to handle large datasets
        if (php_sapi_name() === 'cli') {
            set_time_limit(300); // 5 minutes
        }
        
        // First check if we need to adjust at all
        $totalContacts = $this->countContacts($startDate, $optimalEndDate);
        
        Log::info("Starting time window adjustment", [
            'startDate' => $startDate,
            'endDate' => $optimalEndDate,
            'initialCount' => $totalContacts
        ]);
        
        // If contacts are already in a good range, return immediately
        if ($totalContacts <= 15000 && $totalContacts >= 1000) {
            Log::info("Contact count already in acceptable range, no adjustment needed");
            return [
                'endDate' => $optimalEndDate,
                'totalContacts' => $totalContacts
            ];
        }
        
        // Calculate total time span in seconds
        $startDateTime = Carbon::parse($startDate);
        $endDateTime = Carbon::parse($endDate);
        $totalSeconds = $endDateTime->diffInSeconds($startDateTime);
        
        // If we have too many contacts, use percentage-based reduction
        if ($totalContacts > 15000) {
            // Calculate what percentage of the total window we should keep
            // to get closer to the target batch size
            $targetPercentage = ($targetBatchSize / $totalContacts) * 100;
            
            // Safety check - don't go below 5%
            $targetPercentage = max($targetPercentage, 5);
            
            Log::info("Using percentage-based reduction", [
                'totalContacts' => $totalContacts,
                'targetBatchSize' => $targetBatchSize,
                'targetPercentage' => $targetPercentage . '%',
            ]);
            
            // Calculate how many seconds to keep (percentage of total time span)
            $secondsToKeep = ($totalSeconds * $targetPercentage) / 100;
            
            // Calculate new end date
            $optimalEndDate = $startDateTime->addSeconds($secondsToKeep)->format('Y-m-d\TH:i:s\Z');
            
            // Get the actual contact count with this new window
            $totalContacts = $this->countContacts($startDate, $optimalEndDate);
            
            Log::info("Applied percentage-based reduction", [
                'originalEndDate' => $endDate,
                'newEndDate' => $optimalEndDate,
                'newCount' => $totalContacts,
                'percentageKept' => $targetPercentage . '%'
            ]);
        }
        
        // Fine-tuning phase 
        $attempts = 0;
        $maxAttempts = 10;
        
        // If we're under the minimum, gradually increase
        while ($totalContacts < 1000 && $attempts < $maxAttempts) {
            $currentStart = Carbon::parse($startDate);
            $currentEnd = Carbon::parse($optimalEndDate);
            $fullEnd = Carbon::parse($endDate);
            
            // Calculate current percentage of full time span
            $currentSpan = $currentEnd->diffInSeconds($currentStart);
            $fullSpan = $fullEnd->diffInSeconds($currentStart);
            $currentPercentage = ($currentSpan / $fullSpan) * 100;
            
            // Increase by 50% each time
            $newPercentage = min($currentPercentage * 1.5, 100);
            $newSpan = ($fullSpan * $newPercentage) / 100;
            
            $optimalEndDate = $currentStart->addSeconds($newSpan)->format('Y-m-d\TH:i:s\Z');
            
            // Don't exceed original end date
            if (Carbon::parse($optimalEndDate)->gt($fullEnd)) {
                $optimalEndDate = $endDate;
            }
            
            $totalContacts = $this->countContacts($startDate, $optimalEndDate);
            
            Log::info("Fine-tuning: increased time window by 50%", [
                'newEndDate' => $optimalEndDate,
                'newCount' => $totalContacts,
                'newPercentage' => $newPercentage . '%'
            ]);
            
            $attempts++;
            
            // If we found contacts, we're done
            if ($totalContacts > 0) {
                break;
            }
        }
        
        return [
            'endDate' => $optimalEndDate,
            'totalContacts' => $totalContacts
        ];
    }

    /**
     * Fetch all contacts in batches
     */
    public function getAllContactsInTimeWindow($startDate, $endDate)
    {
        $contacts = [];
        $after = null;
        $hasMore = true;
        $batchSize = 100; // HubSpot's max page size
        $pageCount = 0;
        $maxPages = 100; // Limit to prevent infinite loops
        $maxContactsToRetrieve = 14999; // Make sure it's not a multiple of 1000
        
        Log::info("Starting to fetch contacts", [
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
        
        while ($hasMore && $pageCount < $maxPages && count($contacts) < $maxContactsToRetrieve) {
            try {
                // Respect API rate limits
                if ($pageCount > 0) {
                    usleep(100000); // 100ms delay
                }
                
                $response = $this->searchContacts($startDate, $endDate, $batchSize, $after);
                
                if (isset($response['results']) && !empty($response['results'])) {
                    $contacts = array_merge($contacts, $response['results']);
                    
                    // Check pagination
                    if (isset($response['paging']) && isset($response['paging']['next']['after'])) {
                        $after = $response['paging']['next']['after'];
                    } else {
                        $hasMore = false;
                    }
                } else {
                    $hasMore = false;
                }
                
                $pageCount++;
                
            } catch (\Exception $e) {
                Log::error("Error fetching contacts", [
                    'error' => $e->getMessage(),
                    'page' => $pageCount
                ]);
                
                // If we have some contacts, return them instead of failing completely
                if (count($contacts) > 0) {
                    $hasMore = false;
                } else {
                    // If no contacts were retrieved, throw the exception
                    throw $e;
                }
            }
        }
        
        Log::info("Retrieved " . count($contacts) . " contacts");
        
        return $contacts;
    }

    /**
     * Get or create a sync status record
     */
    public function getSyncStatus($entityType = 'contacts')
    {
        return HubspotSyncStatus::firstOrCreate(
            ['entity_type' => $entityType],
            [
                'last_sync_timestamp' => null,
                'status' => 'idle',
                'start_window' => '2020-03-01T00:00:00Z', // Default start date
                'end_window' => Carbon::now()->format('Y-m-d\TH:i:s\Z')
            ]
        );
    }

    /**
     * Update sync status
     */
    public function updateSyncStatus($entityType, $data)
    {
        $status = $this->getSyncStatus($entityType);
        $status->update($data);
        return $status;
    }
}