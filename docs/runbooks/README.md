# Runbooks Index

## Overview
This directory contains operational runbooks for common incidents. Each runbook follows a standard format:
- **Overview**: What the incident is
- **Symptoms**: How to recognize it
- **Diagnosis Steps**: How to investigate
- **Resolution Steps**: How to fix it
- **Prevention**: How to avoid it
- **Escalation**: When to call for help
- **Related Runbooks**: Links to related issues

## Runbooks

| # | Title | Description | Severity |
|---|-------|-------------|----------|
| [01](01-database-connection-failure.md) | Database Connection Failure | PostgreSQL unavailable | Critical |
| [02](02-high-latency.md) | High API Latency | Response times exceed SLA | High |
| [03](03-queue-backlog.md) | Queue Backlog / Job Failures | Jobs not processing | High |
| [04](04-deployment-failure.md) | Deployment Failure | Deploy leaves system broken | Critical |
| [05](05-ssl-certificate-expiry.md) | SSL Certificate Expiry | Cert expired or expiring | Critical |

## On-Call Contacts
- **Primary**: [Name] - [Phone] - [Slack]
- **Secondary**: [Name] - [Phone] - [Slack]
- **DBA**: [Name] - [Phone] - [Slack]
- **DevOps**: [Name] - [Phone] - [Slack]

## Escalation Policy
1. **Acknowledge** within 5 minutes of alert
2. **Diagnose** using relevant runbook
3. **Resolve** or **Escalate** within 15 minutes
4. **Post-Incident Review** within 48 hours

## Runbook Maintenance
- Review quarterly
- Update after each incident
- Add new runbooks for new incident types
- Test runbooks during game days

## Quick Reference Commands
```bash
# Health check
curl -s https://api.example.com/api/v1/health/ready

# Check logs
tail -f storage/logs/laravel.log

# Queue status
php artisan horizon:status

# Migration status
php artisan migrate:status

# Clear caches
php artisan cache:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```
