<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reports - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
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
                    <a class="nav-link active" href="{{ url('/admin/reports') }}">Reports</a>
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
    <h1>View Reports</h1>
    <a href="{{ url('/admin/dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
    <div id="reports-content">
        <p>Loading reports...</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Fetch all reports and display them
        const endpoints = [
            { url: '/api/reports/revenue', title: 'MRR' },
            { url: '/api/reports/user-growth', title: 'User Growth' },
            { url: '/api/reports/subscription/growth', title: 'Subscription Growth' },
            { url: '/api/reports/task-stats', title: 'Task Stats' },
            { url: '/api/reports/campaign-stats', title: 'Campaign Stats' },
            { url: '/api/reports/user/activity', title: 'User Activity' }
        ];

        const container = document.getElementById('reports-content');
        container.innerHTML = ''; // Clear loading message

        endpoints.forEach(function (endpoint) {
            axios.get(endpoint.url)
                .then(function (response) {
                    const data = response.data;
                    let html = '<h2>' + endpoint.title + '</h2><pre>' + JSON.stringify(data, null, 2) + '</pre><hr>';
                    container.insertAdjacentHTML('beforeend', html);
                })
                .catch(function (error) {
                    container.insertAdjacentHTML('beforeend', '<h2>' + endpoint.title + '</h2><p class="text-danger">Error loading data.</p><hr>');
                    console.error(error);
                });
        });
    });
</script>
</body>
</html>
