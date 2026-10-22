# Runbook: Queue Backlog / Job Failures

## Overview
Jobs accumulate in queues, causing delayed notifications, reports, and async operations.

## Symptoms
- Pulse shows high queue wait times
- Horizon shows large pending job counts
- Users report delayed emails/notifications
- Scheduled reports not generating

## Diagnosis Steps

### 1. Check Horizon Dashboard
```
Visit /horizon
- Check "Pending" jobs count
- Check "Failed" jobs count
- Check "Wait Time" metrics
```

### 2. Check Failed Jobs
```bash
# List failed jobs
php artisan horizon:failed

# Get details of specific failed job
php artisan horizon:show <job-id>
```

### 3. Check Worker Status
```bash
# Check worker processes
ps aux | grep horizon

# Check supervisor status
sudo supervisorctl status
```

### 4. Identify Problematic Jobs
```bash
# Group failed jobs by class
php artisan tinker --execute="
\DB::table('failed_jobs')
    ->selectRaw('connection, queue, payload->>\"\".job\" as job_class, count(*) as count')
    ->groupBy('connection', 'queue', 'job_class')
    ->orderBy('count', 'desc')
    ->get()
    ->each(fn(r) => print_r([r->job_class, r->count]));
"
```

## Resolution Steps

### Restart Workers
```bash
# Graceful restart
php artisan horizon:terminate

# Or via supervisor
sudo supervisorctl restart horizon:*
```

### Scale Workers
```bash
# Temporary scale up
php artisan horizon:scale --max-processes=30

# Check horizon config for permanent scaling
# config/horizon.php
'production' => [
    'supervisor-1' => [
        'connection' => 'redis',
        'queue' => ['high', 'default', 'low'],
        'balance' => 'simple',
        'processes' => 10,  // Increase this
    ],
],
```

### Retry Failed Jobs
```bash
# Retry all failed jobs (use with caution)
php artisan horizon:retry all

# Retry specific job
php artisan horizon:retry <job-id>

# Retry by class
php artisan queue:retry --failed --queue=default
```

### Clear Stuck Jobs
```bash
# If jobs are stuck in "running" state
php artisan horizon:clear running

# Clear old failed jobs (older than 7 days)
php artisan queue:prune-failed --hours=168
```

### Fix Root Cause
```bash
# Check exception details
php artisan horizon:show <job-id>

# Common fixes:
# 1. Increase timeout for slow jobs
# 2. Add missing try/catch
# 3. Fix external API integration
# 4. Increase memory limit
```

## Prevention
- Monitor queue depth via Pulse
- Set up alerts: pending > 1000 for 5 minutes
- Implement job timeouts
- Use unique job IDs to prevent duplicates
- Regular failed job cleanup (cron)

## Escalation
- If backlog > 10,000 jobs: Page on-call
- If critical jobs (billing, emails) failing: Immediate escalation

## Related Runbooks
- [02-High-Latency.md](02-High-Latency.md)
- [04-Deployment-Failure.md](04-Deployment-Failure.md)
