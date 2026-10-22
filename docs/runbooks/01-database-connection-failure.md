# Runbook: Database Connection Failure

## Overview
Database connection failures can cause the entire application to become unavailable. This runbook covers diagnosing and resolving PostgreSQL connection issues.

## Symptoms
- 500 errors on all API endpoints
- "SQLSTATE[HY000] [2002] Connection refused" in logs
- Health check `/api/v1/health/ready` returns 503 with database check failed

## Diagnosis Steps

### 1. Check Database Service Status
```bash
# Check if PostgreSQL is running
sudo systemctl status postgresql

# Check if listening on port 5432
sudo netstat -tlnp | grep 5432

# Check PostgreSQL logs
sudo tail -f /var/log/postgresql/postgresql-*.log
```

### 2. Verify Connection from Application
```bash
# Test connection using Laravel
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connected';"

# Check database config
php artisan config:show database
```

### 3. Check Resource Limits
```bash
# Check PostgreSQL max connections
psql -c "SHOW max_connections;"

# Check current connections
psql -c "SELECT count(*) FROM pg_stat_activity;"

# Check for long-running queries
psql -c "SELECT pid, now() - pg_stat_activity.query_start AS duration, query FROM pg_stat_activity WHERE (now() - pg_stat_activity.query_start) > interval '5 minutes';"
```

## Resolution Steps

### If PostgreSQL is Down
```bash
# Start PostgreSQL
sudo systemctl start postgresql

# Enable auto-start
sudo systemctl enable postgresql
```

### If Connection Limit Reached
```bash
# Increase max_connections in postgresql.conf
# /etc/postgresql/15/main/postgresql.conf
max_connections = 200

# Restart PostgreSQL
sudo systemctl restart postgresql
```

### If Application Pool Exhausted
```bash
# Check Laravel database config for pool settings
# config/database.php
'pgsql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => false,  // Disable persistent connections if issues
    ],
],
```

## Prevention
- Monitor database connections via Pulse dashboard
- Set up alerts for connection count > 80% of max
- Implement connection pooling (PgBouncer) for high traffic
- Regular vacuum/analyze maintenance

## Escalation
- If unable to resolve in 15 minutes: Page on-call DBA
- If data corruption suspected: Stop application, contact DBA immediately

## Related Runbooks
- [02-High-Latency.md](02-High-Latency.md)
- [03-Queue-Backlog.md](03-Queue-Backlog.md)
