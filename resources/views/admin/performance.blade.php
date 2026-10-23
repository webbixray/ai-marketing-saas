@extends('admin.layouts.app')

@section('content')
<div class="container-fluid mb-4">
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Performance Dashboard</h4>
                </div>
                <div class="card-body">
                    <p class="card-text">Performance monitoring is configured.</p>
                    <ul>
                        <li>Application: {{ config('app.name') }}</li>
                        <li>Environment: {{ config('app.env') }}</li>
                        <li>PHP Version: {{ phpversion() }}</li>
                        <li>Server: {{ $_SERVER['SERVER_NAME'] ?? 'unknown' }}</li>
                    </ul>
                    <p>Performance monitoring requires APM integration (New Relic/DataDog)</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">System Status</h4>
                </div>
                <div class="card-body">
                    <p class="card-text">Health: <span id="health-status">Checking...</span></p>
                    <button class="btn btn-sm btn-primary" onclick="checkHealth()">Check Health</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function checkHealth() {
    fetch('/health')
        .then(response => response.json())
        .then(data => {
            const status = document.getElementById('health-status');
            status.innerHTML = data.status === 'healthy' ? '✅ Healthy' : '❌ ' + data.status;
        });
}
</script>
@endsection