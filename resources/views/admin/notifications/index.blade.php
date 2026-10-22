<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ url('/admin/dashboard') }}">Admin Panel</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="{{ url('/admin/plans') }}">Plans</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ url('/admin/reports') }}">Reports</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ url('/admin/notifications') }}">Notifications</a>
                </li>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            Logout
                        </a>
                        <form id="logout-form" action="{{ url('/logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                    </li>
                </ul>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4>
    <h1>Notifications</h1>
    <a href="{{ url('/admin/dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
    <div class="mb-3>
        <a href="{{ url('/admin/notifications/mark-all-as-read') }}" class="btn btn-sm btn-outline-secondary">Mark All as Read</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="table-responsive>
        <table class="table table-striped table-hover>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Data</th>
                    <th>Created At</th>
                    <th>Read?</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @if ($notifications->isEmpty())
                    <tr>
                        <td colspan="5" class="text-center">No notifications found.</td>
                    </tr>
                @else
                    @foreach ($notifications as $notification)
                        <tr>
                            <td>{{ $notification->type }}</td>
                            <td>
                                <pre>{{ json_encode($notification->data, JSON_PRETTY_PRINT) }}</pre>
                            </td>
                            <td>{{ $notification->created_at->toDateTimeString() }}</td>
                            <td>
                                @if ($notification->read_at)
                                    Read
                                @else
                                    Unread
                                @endif
                            </td>
                            <td>
                                <form action="{{ url('/admin/notifications/' . $notification->id . '/mark-as-read') }}" method="POST" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">Mark as Read</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>

    {{ $notifications->links() }}
</div>
</body>
</html>
