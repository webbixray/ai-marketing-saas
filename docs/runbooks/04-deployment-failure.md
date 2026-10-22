# Runbook: Deployment Failure

## Overview
Application deployment fails, leaving the system in an inconsistent or broken state.

## Symptoms
- CI/CD pipeline fails
- Application returns 500 after deploy
- Migrations fail
- Assets not compiled

## Diagnosis Steps

### 1. Check CI/CD Pipeline
```bash
# Check GitHub Actions / GitLab CI logs
# Look for specific error messages
```

### 2. Check Application Logs
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Check nginx/apache logs
tail -f /var/log/nginx/error.log
```

### 3. Verify Environment
```bash
# Check .env file exists and is correct
cat .env | grep -v PASSWORD

# Check config cache
php artisan config:show app.url
```

### 4. Check Migrations
```bash
# Check migration status
php artisan migrate:status

# Check for failed migrations
php artisan migrate:status | grep -v "Ran\|Pending"
```

## Resolution Steps

### Rollback Deployment
```bash
# If using Git tags/releases
git checkout previous-stable-tag
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
sudo supervisorctl restart horizon:*
sudo systemctl reload nginx
```

### Fix Failed Migration
```bash
# Check migration error
php artisan migrate --force 2>&1 | tail -20

# If migration partially applied, fix manually
# Then mark as run
php artisan migrate:status
# Manually insert into migrations table if needed
```

### Clear Caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Rebuild Assets
```bash
npm ci
npm run build
```

### Permission Issues
```bash
# Fix storage permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## Prevention
- Always run migrations in CI before deploy
- Use zero-downtime deployment strategy
- Test migrations on staging first
- Implement deployment health checks
- Use blue-green or rolling deployments

## Escalation
- If rollback fails: Page on-call + DBA
- If data loss risk: Stop immediately, assess

## Related Runbooks
- [01-Database-Connection-Failure.md](01-Database-Connection-Failure.md)
- [05-SSLCertificate-Expiry.md](05-SSLCertificate-Expiry.md)
