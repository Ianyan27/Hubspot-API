<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Contact;

class SearchHubspotContacts extends Command
{
    protected $signature = 'search:hubspot-contacts';
    protected $description = 'Retrieve contacts from HubSpot using the Search API and save them into MariaDB';

    public function handle()
    {
        $this->info('Starting daily HubSpot contacts sync...');
        Log::info('Starting daily HubSpot contacts sync...');

        $token = env('HUBSPOT_PRIVATE_APP_TOKEN');
        if (!$token) {
            $this->error('HubSpot token is missing in .env file.');
            Log::error('HubSpot token is missing in .env file.');
            return 1;
        }

        $url = 'https://api.hubapi.com/crm/v3/objects/contacts/search';
        $after = null;
        $totalContacts = 0;

        do {
            $body = [
                'filterGroups' => [],
                'properties'   => ['delete_flag', 'firstname', 'lastname', 'email', 'gender'],
                'limit'        => 100,
            ];

            if ($after) {
                $body['after'] = $after;
            }

            Log::info('Sending Search API request', ['body' => $body]);
            $response = Http::withToken($token)->post($url, $body);

            if (!$response->successful()) {
                Log::error("Error from HubSpot Search API: " . $response->body());
                $this->error('Error syncing contacts: ' . $response->body());
                return 1;
            }

            $data = $response->json();
            if (!isset($data['results'])) {
                $this->error('No contacts found in response.');
                Log::error('No contacts found in response.');
                return 1;
            }

            foreach ($data['results'] as $record) {
                $contactId  = $record['id'];
                $properties = $record['properties'] ?? [];

                $firstname = $properties['firstname'] ?? null;
                $lastname  = $properties['lastname'] ?? null;
                $email     = $properties['email'] ?? null;
                $gender    = $properties['gender'] ?? null;

                $deleteFlagValue = 'No';
                if (isset($properties['delete_flag'])) {
                    $isTrue = filter_var($properties['delete_flag'], FILTER_VALIDATE_BOOLEAN);
                    $deleteFlagValue = $isTrue ? 'Yes' : 'No';
                }

                // Sync contact to database
                Contact::updateOrCreate(
                    ['contact_id' => $contactId],
                    [
                        'firstname'   => $firstname,
                        'lastname'    => $lastname,
                        'email'       => $email,
                        'gender'      => $gender,
                        'delete_flag' => $deleteFlagValue,
                    ]
                );

                $totalContacts++;
                Log::info("Synced contact", ['contact_id' => $contactId]);
            }

            $after = $data['paging']['next']['after'] ?? null;
            Log::info("Pagination cursor", ['after' => $after]);
        } while ($after);

        $this->info("Successfully synced {$totalContacts} contacts.");
        Log::info("Successfully synced {$totalContacts} contacts.");
        return 0;
    }
}
