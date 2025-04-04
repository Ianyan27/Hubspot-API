@extends('layouts.app')
@section('content')
    <h1>HubSpot Contacts</h1>

    <!-- Display any error messages if the request failed -->
    @isset($error)
        <p style="color: red;">Error: {{ $error }}</p>
    @endisset

    <!-- Buttons to refresh contacts and export CSV -->
    <a href="{{ route('hubspot.search.contacts') }}">Refresh Contacts</a>
    <a href="{{ route('hubspot.contacts.export') }}">Export CSV</a>

    <table border="1" cellpadding="8" cellspacing="0" style="margin-top:20px; width:100%;">
        <thead>
            <tr>
                <th>ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th style="text-align: center;">Gender</th>
                <th style="text-align: center;">Delete Flag</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contacts as $contact)
                <tr class="{{ $contact->marked_deleted ? 'marked-deleted' : '' }}">
                    <td>{{ $contact->contact_id }}</td>
                    <td>{{ $contact->first_name ?? '' }}</td>
                    <td>{{ $contact->last_name ?? '' }}</td>
                    <td>{{ $contact->email ?? '' }}</td>
                    <td style="text-align: center;">{{ $contact->gender }}</td>
                    <td style="text-align: center;">{{ $contact->delete_flag }}</td>
                    <td style="text-align: center;">
                        <!-- Mark as Deleted button -->
                        <form action="{{ route('contacts.markDelete', $contact->contact_id) }}" method="POST"
                            style="display:inline;">
                            @csrf
                            <button type="submit"><i class="fa-solid fa-trash" style="color: red;"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr style="text-align: center;">
                    <td colspan="7">No contacts found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Pagination links -->
    <div class="pagination">
        {{ $contacts->links() }}
    </div>
@endsection
