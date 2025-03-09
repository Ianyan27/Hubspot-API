<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HubspotController extends Controller
{
    public function index(Request $request)
    {
        // Retrieve your HubSpot Private App Token from the .env file
        $token = env('HUBSPOT_PRIVATE_APP_TOKEN');

        // Construct the API URL with the properties you want
        $url = 'https://api.hubapi.com/crm/v3/objects/contacts?properties=delete_flag,firstname,lastname,email&limit=100';

        // Make the GET request
        $response = Http::withToken($token)->get($url);

        // If the request fails, show an error
        if (!$response->successful()) {
            // Pass the error message to the view
            return view('hubspot.index', [
                'contacts' => [],
                'error'    => $response->body()
            ]);
        }

        // Parse the JSON response
        $data = $response->json();

        // The contacts are typically found in $data['results']
        $contacts = $data['results'] ?? [];

        // Pass the contacts array to the view
        return view('hubspot.index', compact('contacts'));
    }
}
