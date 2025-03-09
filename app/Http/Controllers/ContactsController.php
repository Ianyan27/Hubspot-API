<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContactsController extends Controller
{
    public function index()
    {
        // Retrieve all contacts from the database
        $contacts = Contact::all();
        return view('contacts', compact('contacts'));
    }

    public function destroy($id)
    {
        $contact = Contact::findOrFail($id);

        // Delete the contact from HubSpot
        $token = env('HUBSPOT_PRIVATE_APP_TOKEN');
        $hubspotUrl = "https://api.hubapi.com/crm/v3/objects/contacts/{$contact->contact_id}";

        $response = Http::withToken($token)->delete($hubspotUrl);

        if (!$response->successful()) {
            // Log the error but continue to remove the local record
            Log::error("Failed to delete HubSpot contact (ID: {$contact->contact_id}): " . $response->body());
        }

        $contact->delete();

        return redirect()->route('contacts.index')->with('success', 'Contact deleted successfully.');
    }

    public function exportContactsToCSV()
    {
        // Retrieve contacts from the database
        $contacts = Contact::all();
        $filename = 'contacts_' . date('Ymd_His') . '.csv';
        $filepath = storage_path("app/{$filename}");

        // Open a file handle for writing
        $handle = fopen($filepath, 'w+');

        // Write CSV header row
        fputcsv($handle, ['Contact ID', 'First Name', 'Last Name', 'Email']);

        // Write each contact's data
        foreach ($contacts as $contact) {
            fputcsv($handle, [
                $contact->contact_id,
                $contact->firstname,
                $contact->lastname,
                $contact->email
            ]);
        }

        // Close the file handle
        fclose($handle);

        // Return the file as a download and delete it after sending
        return response()->download($filepath)->deleteFileAfterSend(true);
    }

    public function deleteMultiple(Request $request)
    {
        $contactIds = $request->input('selected_contacts', []);

        if (empty($contactIds)) {
            return redirect()->route('contacts.index')->with('error', 'No contacts selected for deletion.');
        }

        // Retrieve your HubSpot token from .env if you plan to delete from HubSpot too.
        $token = env('HUBSPOT_PRIVATE_APP_TOKEN');

        foreach ($contactIds as $id) {
            // Find the contact in your database
            $contact = Contact::find($id);
            if ($contact) {
                // Optionally delete the contact from HubSpot
                $hubspotUrl = "https://api.hubapi.com/crm/v3/objects/contacts/{$contact->contact_id}";
                $response = Http::withToken($token)->delete($hubspotUrl);
                if (!$response->successful()) {
                    Log::error("Failed to delete HubSpot contact ID {$contact->contact_id}: " . $response->body());
                    // Optionally, decide whether to stop or continue deletion.
                }
                // Delete the contact locally
                $contact->delete();
            }
        }

        return redirect()->route('contacts.index')->with('success', 'Selected contacts deleted successfully.');
    }
}
