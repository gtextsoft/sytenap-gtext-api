<!DOCTYPE html>
<html>
<head>
    <title>External Users</title>
</head>
<body>
    <h2>External Users (from API)</h2>

    <form method="get" action="{{ url('/external-users') }}">
        <input type="text" name="search" placeholder="Search users..." value="{{ request('search') }}">
        <button type="submit">Search</button>
    </form>

    <table border="1" cellpadding="6" style="margin-top: 10px;">
        <tr>
            <th>ID</th>
            <th>Firstname</th>
            <th>Lastname</th>
            <th>Email</th>
            <th>Gender</th>
            <th>City</th>
        </tr>

        @forelse ($users as $user)
            <tr>
                <td>{{ $user['id'] }}</td>
                <td>{{ $user['firstname'] }}</td>
                <td>{{ $user['lastname'] }}</td>
                <td>{{ $user['email'] }}</td>
                <td>{{ $user['gender'] }}</td>
                <td>{{ $user['city'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="text-align:center;">No users found.</td>
            </tr>
        @endforelse
    </table>

    @if(!empty($meta))
        <p>
            Page {{ $meta['current_page'] ?? 1 }} of {{ $meta['last_page'] ?? 1 }}
        </p>
    @endif
</body>
</html>
