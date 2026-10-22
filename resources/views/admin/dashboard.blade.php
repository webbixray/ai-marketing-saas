<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/minimalist-font-awesome.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/admin') }}">Weavixray Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarnav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/admin') }}">Overview</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/admin/campaigns') }}">Campaigns</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/admin/clients') }}">Clients</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/admin/reports') }}">Reports</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <main class="container my-4">
        <div class="row">
            <!-- Stats Cards -->
            <div class="col-12 col-md-6 col-lg-3 mb-3">
                <div class="card text-white bg-primary o-hidden h-100 inner">
                    <div class="card-body">
                        <div class="card-text">{{ number_format($stats['total_campaigns']) }}</div>
                        <div class="small text-white-50">Total Campaigns</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3 mb-3">
                <div class="card text-white bg-success o-hidden h-100 inner">
                    <div class="card-body">
                        <div class="card-text">{{ number_format($stats['active_campaigns']) }}</div>
                        <div class="small text-white-50">Active Campaigns</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3 mb-3">
                <div class="card text-white bg-info o-hidden h-100 inner">
                    <div class="card-body">
                        <div class="card-text">{{ number_format($stats['total_clients']) }}</div>
                        <div class="small text-white-50">Total Clients</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3 mb-3">
                <div class="card text-white bg-warning o-hidden h-100 inner">
                    <div class="card-body">
                        <div class="card-text">${{ number_format($stats['total_spend'], 0) }}</div>
                        <div class="small text-white-50">Total Spend</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Revenue Card -->
        <div class="row mb-3">
            <div class="col-12 col-md-6 col-lg-4 mb-3">
                <div class="card text-white bg-danger o-hidden h-100 inner">
                    <div class="card-body">
                        <div class="card-text">{{ '$' . number_format($stats['total_revenue'], 0) }}</div>
                        <div class="small text-white-50">Total Revenue</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 mb-3">
                <div class="card text-white bg-secondary o-hidden h-100 inner">
                    <div class="card-body">
                        <div class="card-text">{{ number_format($stats['cac'], 2) }}</div>
                        <div class="small text-white-50">Average CAC</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 mb-3">
                <div class="card text-white bg-success o-hidden h-100 inner">
                    <div class="card-body">
                        <div class="card-text">{{ $stats['roas'] }}x ROAS</div>
                        <div class="small text-white-50">Return on Ad Spend</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Conversion Rate -->
        <div class="row mb-3">
            <div class="col-12 col-lg-4 mb-3">
                <div class="card text-white bg-info o-hidden h-100 inner">
                    <div class="card-body">
                        <div class="card-text">{{ $stats['conversion_rate'] }}% conversion rate</div>
                        <div class="small text-white-50">Overall Conversion Rate</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Funnel Chart -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card o-hidden border-0 rounded-lg shadow-xl">
                    <div class="card-body">
                        <h6 class="m-0 font-weight-6 text-primary">Conversion Funnel</h6>
                        <div class="chart-area">
                            <canvas id="funnelChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script>
        // Initialize charts
        Highcharts.chart('funnelChart', {
            chart: { type: 'funnel' },
            title: { text: 'Sales Funnel' },
            plotOptions: {
                funnel: { allowPointSelect: true, size: '80%', dataLabels: { enabled: true, format: '{point.y:.1f}%' } }
            },
            series: [{
                name: 'Funnel',
                data: [{{ $funnel['impressions'] }}, {{ $funnel['clicks'] }}, {{ $funnel['leads'] }}, {{ $funnel['opportunities'] }}, {{ $funnel['closed_won'] }}]
            }]
        });
    </script>
</body>
</html>