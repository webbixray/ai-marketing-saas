# Runbook: SSL Certificate Expiry

## Overview
SSL/TLS certificate expires or is about to expire, causing browser warnings and API failures.

## Symptoms
- Browser shows "Not Secure" warning
- API clients fail with certificate errors
- Monitoring alerts: "SSL cert expires in < 30 days"
- Health check fails on HTTPS endpoints

## Diagnosis Steps

### 1. Check Certificate Expiry
```bash
# Check certificate details
openssl s_client -connect api.example.com:443 -servername api.example.com < /dev/null 2>/dev/null | openssl x509 -noout -dates

# Check all domains
for domain in api.example.com app.example.com; do
  echo "=== $domain ==="
  openssl s_client -connect $domain:443 -servername $domain < /dev/null 2>/dev/null | openssl x509 -noout -subject -dates
done
```

### 2. Check Certificate Files
```bash
# Check Let's Encrypt certificates
ls -la /etc/letsencrypt/live/

# Check certbot status
sudo certbot certificates
```

### 3. Check Auto-Renewal
```bash
# Check certbot timer
systemctl status certbot.timer

# Check renewal logs
journalctl -u certbot -f
```

## Resolution Steps

### Manual Renewal
```bash
# Renew all certificates
sudo certbot renew --force-renewal

# Renew specific domain
sudo certbot certonly --force-renewal -d api.example.com
```

### Reload Nginx
```bash
# Test config
sudo nginx -t

# Reload
sudo systemctl reload nginx
```

### Verify Renewal
```bash
# Check new expiry
openssl s_client -connect api.example.com:443 -servername api.example.com < /dev/null 2>/dev/null | openssl x509 -noout -dates
```

## Prevention
- Set up monitoring alert at 30 days before expiry
- Ensure certbot timer is enabled and running
- Test renewal monthly
- Use wildcard certificates where possible
- Document certificate locations and renewal process

## Escalation
- If certificate expired: Emergency - page on-call immediately
- If renewal fails: Page on-call + DevOps

## Related Runbooks
- [04-Deployment-Failure.md](04-Deployment-Failure.md)
