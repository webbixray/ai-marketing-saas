<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Plans - Admin Panel</title>
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
    <h1>Manage Plans</h1>
    <a href="{{ url('/admin/plans/create') }}" class="btn btn-success">New Plan</a>
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Stripe Product ID</th>
                <th>Stripe Price ID</th>
                <th>Active</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plans as $plan)
                <tr>
                    <td>{{ $plan->name }}</td>
                    <td>{{ $plan->description }}</td>
                    <td>{{ $plan->stripe_product_id }}</td>
                    <td>{{ $plan->stripe_price_id }}</td>
                    <td>{{ $plan->active ? 'Yes' : 'No' }}</td>
                    <td>
                        <a href="{{ url('/admin/plans/' . $plan->id . '/edit') }}" class="btn btn-sm btn-info">Edit</a>
                        <form action="{{ url('/admin/plans/' . $plan->id) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <a href="{{ url('/admin/dashboard') }}" class="btn btn-link">Back to Dashboard</a>
</div>
</body>
</html>
