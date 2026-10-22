<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Plan - Admin Panel</title>
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
                <ul class="navbar-nav ms-auto>
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
    <h1>Edit Plan</h1>
    <a href="{{ url('/admin/plans') }}" class="btn btn-secondary">Back to List</a>
    <form method="POST" action="{{ url('/admin/plans/' . $plan->id) }}">
        @csrf
        @method('PUT')
        <div class="mb-3>
            <label for="name">Plan Name</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ $plan->name }}" required>
        </div>
        <div class="mb-3>
            <label for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3">{{ $plan->description }}</textarea>
        </div>
        <div class="mb-3>
            <label for="amount">Amount (cents)</label>
            <input type="number" class="form-control" id="amount" name="amount" min="0" value="0">
        </div>
        <div class="mb-3>
            <label for="currency">Currency</label>
            <select class="form-control" id="currency" name="currency">
                <option value="usd">USD</option>
                <option value="eur">EUR</option>
                <option value="gbp">GBP</option>
            </select>
        </div>
        <div class="mb-3>
            <label for="interval">Interval</label>
            <select class="form-control" id="interval" name="interval">
                <option value="day">Day</option>
                <option value="week">Week</option
</option>
                <option value="month">Month</option>
                <option value="year">Year</option>
            </select>
        </div>
        <div class="mb-3>
            <label for="interval_count">Interval Count</label>
            <input type="number" class="form-control" id="interval_count" name="interval_count" min="1" value="1">
        </div>
        <button type="submit" class="btn btn-primary">Update Plan</button>
    </form>
</div>
</body>
</html>
