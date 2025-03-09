<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Contact;

class SyncHubspotContacts extends Command
{
    protected $signature = 'search:hubspot-contacts';
    protected $description = 'Retrieve contacts from HubSpot using the Search API and save them into MariaDB';

    public function handle()
    {
        $this->info('Starting HubSpot contacts search and sync...');
        Log::info('Starting HubSpot contacts search and sync...');

        // Retrieve the HubSpot token from .env
        $token = env('HUBSPOT_PRIVATE_APP_TOKEN');
        if (!$token) {
            $this->error('HubSpot token is missing in .env file.');
            Log::error('HubSpot token is missing in .env file.');
            return 1;
        }

        // HubSpot Search API endpoint for contacts
        $url = 'https://api.hubapi.com/crm/v3/objects/contacts/search';
        $after = null;
        $totalContacts = 0;

        do {
            // Prepare the request body for the Search API.
            $body = [
                'filterGroups' => [],
                'properties'   => ['delete_flag', 'firstname', 'lastname', 'email'],
                'limit'        => 100,
            ];

            // If a pagination cursor exists, add it to the body.
            if ($after) {
                $body['after'] = $after;
            }

            Log::info('Sending search API request to HubSpot', ['body' => $body]);
            $response = Http::withToken($token)->post($url, $body);

            if (!$response->successful()) {
                Log::error("Error retrieving data from HubSpot Search API: " . $response->body());
                $this->error('Error retrieving data from HubSpot Search API: ' . $response->body());
                return 1;
            }

            $data = $response->json();
            Log::info('Received data from HubSpot Search API', ['data' => $data]);

            if (!isset($data['results'])) {
                $this->error('No contacts found in the response.');
                Log::error('No contacts found in the response.');
                return 1;
            }

            // Loop through each retrieved contact and process it.
            foreach ($data['results'] as $record) {
                $contactId  = $record['id'];
                $properties = $record['properties'] ?? [];

                $firstname = $properties['firstname'] ?? null;
                $lastname  = $properties['lastname'] ?? null;
                $email     = $properties['email'] ?? null;

                // Convert the delete_flag property into a string ("Yes" or "No")
                if (isset($properties['delete_flag'])) {
                    $isTrue = filter_var($properties['delete_flag'], FILTER_VALIDATE_BOOLEAN);
                    $deleteFlagValue = $isTrue ? 'Yes' : 'No';
                } else {
                    $deleteFlagValue = 'No';
                }

                Log::info("Processing contact", [
                    'contact_id'  => $contactId,
                    'firstname'   => $firstname,
                    'lastname'    => $lastname,
                    'email'       => $email,
                    'delete_flag' => $deleteFlagValue,
                ]);

                // Save the contact to the database using updateOrCreate.
                Contact::updateOrCreate(
                    ['contact_id' => $contactId],
                    [
                        'first_name'  => $firstname,
                        'last_name'   => $lastname,
                        'email'       => $email,
                        'delete_flag' => $deleteFlagValue,
                    ]
                );

                $totalContacts++;
                Log::info("Synced contact", ['contact_id' => $contactId]);
            }

            // Handle pagination: use the "after" token if present.
            $after = $data['paging']['next']['after'] ?? null;
            Log::info('Pagination cursor', ['after' => $after]);
        } while ($after);

        $this->info("Successfully synced {$totalContacts} contacts.");
        Log::info("Successfully synced {$totalContacts} contacts.");
        return 0;
    }
}
