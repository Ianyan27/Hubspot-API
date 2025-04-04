<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HubSpot Contacts</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background: #f2f2f2;
        }
        .error {
            color: red;
        }
        .button {
            padding: 6px 12px;
            background: #007BFF;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <h1>HubSpot Contacts</h1>

    @if(isset($error))
        <p class="error">{{ $error }}</p>
    @endif

    <!-- Refresh Contacts button -->
    <a class="button" href="{{ route('hubspot.contacts.display') }}">Refresh Contacts</a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Gender</th>
                <th>Delete Flag</th>
                <th>Created Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contacts as $contact)
                <tr>
                    <td>{{ $contact['id'] }}</td>
                    <td>{{ $contact['properties']['firstname'] ?? '' }}</td>
                    <td>{{ $contact['properties']['lastname'] ?? '' }}</td>
                    <td>{{ $contact['properties']['email'] ?? '' }}</td>
                    <td>{{ $contact['properties']['gender'] ?? '' }}</td>
                    <td>
                        @if(isset($contact['properties']['delete_flag']))
                            {{ filter_var($contact['properties']['delete_flag'], FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No' }}
                        @else
                            No
                        @endif
                    </td>
                    <td>{{ $contact['properties']['createdate'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">No contacts found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
