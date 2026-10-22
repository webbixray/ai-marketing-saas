# Runbook: High API Latency

## Overview
API response times exceed SLA thresholds (>500ms p95). This affects user experience and can cause timeouts.

## Symptoms
- Pulse dashboard shows high p95/p99 latency
- Alert: "API p95 latency > 500ms for 5 minutes"
- User complaints about slow UI

## Diagnosis Steps

### 1. Identify Slow Endpoints
```bash
# Check Pulse dashboard: /pulse/slow-requests
# Or query slow queries
php artisan tinker --execute="
\DB::table('pulse_slow_requests')
    ->orderBy('duration_ms', 'desc')
    ->limit(20)
    ->get(['url', 'duration_ms', 'method', 'created_at'])
    ->each(fn(r) => print_r([r->url, r->duration_ms . 'ms', r->method]));
"
```

### 2. Check Database Performance
```bash
# Check slow queries in Pulse: /pulse/slow-queries
# Or manually
psql -c "
SELECT query, calls, mean_exec_time, total_exec_time
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 10;
"
```

### 3. Check Queue Latency
```bash
# Check Pulse: /pulse/queues
# Look for high wait times or failed jobs
php artisan horizon:status
```

### 4. Check External API Calls
```bash
# Check slow outgoing requests in Pulse: /pulse/slow-outgoing-requests
# Look for Stripe, Meta API, Google API latency
```

### 5. Check Resource Usage
```bash
# CPU/Memory
htop
free -h

# Disk I/O
iostat -x 1 5

# Network
iftop
```

## Resolution Steps

### Database Issues
```bash
# Add missing indexes
php artisan migrate  # Check for missing indexes in migrations

# Analyze tables
psql -c "ANALYZE;"

# Consider read replica for reporting queries
```

### N+1 Query Issues
```php
// Use eager loading
Campaign::with(['client', 'assignee'])->get();
// Instead of
Campaign::all()->each(fn(c) => $c->client);
```

### Cache Misses
```bash
# Check cache hit rate in Pulse
# Increase cache TTL for expensive queries
# Warm cache on deploy
php artisan cache:warm
```

### External API Timeouts
```php
// Add timeouts to HTTP client
Http::timeout(10)->retry(3, 100)->get($url);

// Implement circuit breaker
```

### Queue Backlog
```bash
# Scale workers
php artisan horizon:scale --max-processes=20

# Clear failed jobs if safe
php artisan horizon:clear failed
```

## Prevention
- Add database indexes for common queries
- Use eager loading consistently
- Implement response caching for reports
- Set reasonable timeouts on external calls
- Monitor Pulse daily

## Escalation
- If latency > 2s p95 for 10 minutes: Page on-call
- If > 50% requests timeout: Emergency scale-up

## Related Runbooks
- [01-Database-Connection-Failure.md](01-Database-Connection-Failure.md)
- [03-Queue-Backlog.md](03-Queue-Backlog.md)
