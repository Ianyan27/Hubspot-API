<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts List</title>
</head>
<body>
    <h1>Contacts List</h1>

    @if(session('success'))
        <p style="color: green;">{{ session('success') }}</p>
    @endif
    @if(session('error'))
        <p style="color: red;">{{ session('error') }}</p>
    @endif

    <a href="{{ route('contacts.export') }}">Export Contacts to CSV</a>

    <form action="{{ route('contacts.deleteMultiple') }}" method="POST" onsubmit="return confirm('Are you sure you want to delete the selected contacts?');">
        @csrf
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Select</th>
                    <th>Contact ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email</th>
                    <th>Delete Flag</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contacts as $contact)
                    <tr>
                        <td>
                            <input type="checkbox" name="selected_contacts[]" value="{{ $contact->contact_id }}">
                        </td>
                        <td>{{ $contact->contact_id }}</td>
                        <td>{{ $contact->firstname }}</td>
                        <td>{{ $contact->lastname }}</td>
                        <td>{{ $contact->email }}</td>
                        <td>{{ $contact->delete_flag ? 'Yes' : 'No' }}</td>
                        <td>
                            <!-- Individual deletion button -->
                            <button type="button" onclick="deleteContact('{{ $contact->contact_id }}')">Delete</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <br>
        <button type="submit">Delete Selected Contacts</button>
    </form>

    <script>
        function deleteContact(contactId) {
            if (confirm('Are you sure you want to delete this contact?')) {
                fetch('/contacts/' + contactId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if(response.ok) {
                        location.reload();
                    } else {
                        alert('Deletion failed.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Deletion failed.');
                });
            }
        }
    </script>
</body>
</html>
