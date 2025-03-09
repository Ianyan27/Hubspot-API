<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HubSpot Contacts</title>
    <!-- (Optional) Include Bootstrap or your preferred CSS framework -->
</head>
<body>
    <h1>HubSpot Contacts</h1>

    <!-- Display any error messages if the request failed -->
    @isset($error)
        <p style="color: red;">Error: {{ $error }}</p>
    @endisset

    <!-- A "Refresh Contacts" button that simply reloads this page -->
    <a href="{{ route('hubspot.contacts') }}">Refresh Contacts</a>

    <table border="1" cellpadding="8" cellspacing="0" style="margin-top:20px;">
        <thead>
            <tr>
                <th>ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Delete Flag</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contacts as $contact)
                <tr>
                    <td>{{ $contact['id'] }}</td>
                    <td>{{ $contact['properties']['firstname'] ?? '' }}</td>
                    <td>{{ $contact['properties']['lastname'] ?? '' }}</td>
                    <td>{{ $contact['properties']['email'] ?? '' }}</td>
                    <td>
                        @if(isset($contact['properties']['delete_flag']))
                            {{-- If it's "true", show "Yes", otherwise "No" --}}
                            {{ filter_var($contact['properties']['delete_flag'], FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No' }}
                        @else
                            No
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No contacts found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
