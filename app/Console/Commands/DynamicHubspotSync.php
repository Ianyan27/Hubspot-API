<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Contact;

class DynamicHubspotSync extends Command
{
    protected $signature = 'sync:hubspot-dynamic';
    protected $description = 'Dynamically sync contacts from HubSpot using date filters to adjust bulk size';

    // Updated overall range: March 1, 2025 to May 31, 2025
    protected $overallStart = '2020-03-01T00:00:00Z';
    protected $overallEnd   = '2022-05-31T23:59:59Z';

    // For testing, use lower thresholds:
    protected $minThreshold = 10;
    protected $maxThreshold = 20;

    // Decrement/increment intervals (in seconds)
    protected $largeDecrement = 3600; // 1 hour
    protected $smallDecrement = 5;    // 5 seconds
    protected $smallIncrement = 60;   // 1 minute

    // HubSpot API token from .env
    protected $token;

    public function handle()
    {
        $this->info('Starting dynamic HubSpot contacts sync...');
        Log::info('Starting dynamic HubSpot contacts sync...');

        $this->token = env('HUBSPOT_PRIVATE_APP_TOKEN');
        if (!$this->token) {
            $this->error('HubSpot token is missing in .env file.');
            Log::error('HubSpot token is missing in .env file.');
            return 1;
        }

        $currentStart = $this->overallStart;
        $overallEndTimestamp = strtotime($this->overallEnd);
        $totalSynced = 0;

        while (strtotime($currentStart) < $overallEndTimestamp) {
            // Start with an initial window of 1 hour.
            $currentEnd = date('c', strtotime($currentStart) + 3600);
            if (strtotime($currentEnd) > $overallEndTimestamp) {
                $currentEnd = $this->overallEnd;
            }

            $count = $this->getContactCount($currentStart, $currentEnd);
            Log::info("Initial window [$currentStart, $currentEnd] returned count: $count");

            // Shrink the window if count is above maximum threshold
            while ($count > $this->maxThreshold && strtotime($currentEnd) > strtotime($currentStart)) {
                $currentEnd = date('c', strtotime($currentEnd) - $this->largeDecrement);
                $count = $this->getContactCount($currentStart, $currentEnd);
                Log::info("Shrinking window (large decrement)", ['start' => $currentStart, 'end' => $currentEnd, 'count' => $count]);
            }

            // Fine-tune with small decrement if still too high
            while ($count > $this->maxThreshold && strtotime($currentEnd) > strtotime($currentStart)) {
                $currentEnd = date('c', strtotime($currentEnd) - $this->smallDecrement);
                $count = $this->getContactCount($currentStart, $currentEnd);
                Log::info("Fine-tuning window (small decrement)", ['start' => $currentStart, 'end' => $currentEnd, 'count' => $count]);
            }

            // Expand the window if count is below minimum threshold
            // Expand the window if count is below minimum threshold
            while ($count < $this->minThreshold && strtotime($currentEnd) < $overallEndTimestamp) {
                $prevEnd = $currentEnd;
                $currentEnd = date('c', strtotime($currentEnd) + $this->smallIncrement);
                if (strtotime($currentEnd) > $overallEndTimestamp) {
                    $currentEnd = $this->overallEnd;
                }
                $count = $this->getContactCount($currentStart, $currentEnd);
                Log::info("Expanding window", [
                    'start'    => $currentStart,
                    'prev_end' => $prevEnd,
                    'new_end'  => $currentEnd,
                    'count'    => $count
                ]);
                // If incrementing didn't change the window, break to prevent an infinite loop.
                if ($prevEnd === $currentEnd) {
                    Log::warning("Window expansion did not change the currentEnd; breaking out of the loop.");
                    break;
                }
            }


            Log::info("Final window [$currentStart, $currentEnd] with count: $count");

            // Sync contacts for this window and accumulate total synced
            $synced = $this->syncContactsForWindow($currentStart, $currentEnd);
            $totalSynced += $synced;
            Log::info("Synced $synced contacts for window [$currentStart, $currentEnd]");

            // Move to next window by setting currentStart to currentEnd
            $currentStart = $currentEnd;
        }

        $this->info("Successfully synced a total of $totalSynced contacts.");
        Log::info("Successfully synced a total of $totalSynced contacts.");
        return 0;
    }

    /**
     * Retrieve the total number of contacts from HubSpot for a given time window.
     */
    protected function getContactCount($start, $end)
    {
        $url = 'https://api.hubapi.com/crm/v3/objects/contacts/search';
        $body = [
            "filterGroups" => [
                [
                    "filters" => [
                        [
                            "propertyName" => "createdate",
                            "operator" => "GT",
                            "value" => $start,
                        ],
                        [
                            "propertyName" => "createdate",
                            "operator" => "LT",
                            "value" => $end,
                        ]
                    ]
                ]
            ],
            "properties" => ["firstname", "lastname", "email", "gender", "delete_flag"],
            "limit" => 1 // Only one record is needed to get the total count
        ];

        $response = Http::withToken($this->token)->post($url, $body);
        if ($response->successful()) {
            $data = $response->json();
            return isset($data['total']) ? (int)$data['total'] : count($data['results'] ?? []);
        }
        return 0;
    }

    /**
     * Sync contacts from HubSpot for the specified time window and update the database.
     */
    protected function syncContactsForWindow($start, $end)
    {
        $url = 'https://api.hubspot.com/crm/v3/objects/contacts/search';
        $syncedCount = 0;
        $after = null;

        do {
            $body = [
                "filterGroups" => [
                    [
                        "filters" => [
                            [
                                "propertyName" => "createdate",
                                "operator" => "GT",
                                "value" => $start,
                            ],
                            [
                                "propertyName" => "createdate",
                                "operator" => "LT",
                                "value" => $end,
                            ]
                        ]
                    ]
                ],
                "properties" => ["firstname", "lastname", "email", "gender", "delete_flag"],
                "limit" => 100,
            ];

            if ($after) {
                $body["after"] = $after;
            }

            $response = Http::withToken($this->token)->post($url, $body);
            if (!$response->successful()) {
                Log::error("Error syncing window [$start, $end]: " . $response->body());
                break;
            }

            $data = $response->json();
            if (!isset($data['results'])) {
                break;
            }

            foreach ($data['results'] as $record) {
                $contactId = $record['id'];
                $props = $record['properties'] ?? [];
                $firstname = $props['firstname'] ?? null;
                $lastname  = $props['lastname'] ?? null;
                $email     = $props['email'] ?? null;
                $gender    = $props['gender'] ?? null;
                $deleteFlagValue = 'No';
                if (isset($props['delete_flag'])) {
                    $isTrue = filter_var($props['delete_flag'], FILTER_VALIDATE_BOOLEAN);
                    $deleteFlagValue = $isTrue ? 'Yes' : 'No';
                }

                \App\Models\Contact::updateOrCreate(
                    ['contact_id' => $contactId],
                    [
                        'firstname'   => $firstname,
                        'lastname'    => $lastname,
                        'email'       => $email,
                        'gender'      => $gender,
                        'delete_flag' => $deleteFlagValue,
                    ]
                );
                $syncedCount++;
            }

            $after = $data['paging']['next']['after'] ?? null;
        } while ($after);

        return $syncedCount;
    }
}
