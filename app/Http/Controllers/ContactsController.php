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

    public function exportContactsToCSV()
    {
        // Retrieve only the contacts that are NOT marked as deleted
        $contacts = Contact::where('marked_deleted', false)->get();
        $filename = 'contacts_' . date('Ymd_His') . '.csv';
        $filepath = storage_path("app/{$filename}");
        $handle = fopen($filepath, 'w+');

        // CSV header
        fputcsv($handle, ['Contact ID', 'First Name', 'Last Name', 'Email', 'Delete Flag']);

        foreach ($contacts as $contact) {
            fputcsv($handle, [
                $contact->contact_id,
                $contact->first_name,
                $contact->last_name,
                $contact->email,
                $contact->delete_flag
            ]);
        }

        fclose($handle);

        return response()->download($filepath)->deleteFileAfterSend(true);
    }

    public function markDelete($id)
    {
        $contact = Contact::findOrFail($id);
        $contact->marked_deleted = true;
        $contact->save();

        return redirect()->back()->with('success', 'Contact marked as deleted.');
    }
}
