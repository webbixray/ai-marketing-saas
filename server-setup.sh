#!/bin/bash
# =============================================
# Server Setup & Hardening Script
# AI Marketing SaaS — Production VPS
# Run as root on fresh Ubuntu 22.04/24.04 server
# =============================================

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $*"; }
warn() { echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $*"; }
error() { echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $*"; exit 1; }

# Check root
[[ $EUID -eq 0 ]] || error "Must run as root"

log "Starting server setup for AI Marketing SaaS..."

# ---------------------------------------------
# 1. System Update & Base Packages
# ---------------------------------------------
log "Updating system packages..."
apt-get update && apt-get upgrade -y

log "Installing base packages..."
apt-get install -y \
    curl \
    wget \
    git \
    unzip \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg \
    lsb-release \
    ufw \
    fail2ban \
    htop \
    iotop \
    net-tools \
    dnsutils \
    certbot \
    python3-certbot-nginx \
    logrotate \
    rsync \
    jq

# ---------------------------------------------
# 2. Docker Installation
# ---------------------------------------------
log "Installing Docker..."
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
chmod a+r /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Verify Docker
docker --version
docker compose version

# ---------------------------------------------
# 3. Create Application User
# ---------------------------------------------
log "Creating application user..."
id -u appuser &>/dev/null || useradd -m -s /bin/bash -u 1000 appuser
usermod -aG docker appuser

# ---------------------------------------------
# 4. SSH Hardening
# ---------------------------------------------
log "Hardening SSH..."
cp /etc/ssh/sshd_config /etc/ssh/sshd_config.backup

cat > /etc/ssh/sshd_config.d/99-hardening.conf << 'EOF'
# SSH Hardening
Port 22
Protocol 2
PermitRootLogin no
PubkeyAuthentication yes
PasswordAuthentication no
PermitEmptyPasswords no
ChallengeResponseAuthentication no
UsePAM yes
X11Forwarding no
AllowAgentForwarding no
AllowTcpForwarding no
PermitTunnel no
ClientAliveInterval 300
ClientAliveCountMax 2
MaxAuthTries 3
MaxSessions 2
LoginGraceTime 30
Banner /etc/issue.net
AcceptEnv LANG LC_*
Subsystem sftp /usr/lib/openssh/sftp-server
EOF

# Create banner
cat > /etc/issue.net << 'EOF'
***************************************************************************
*                    AUTHORIZED ACCESS ONLY                               *
*                                                                         *
* This system is for authorized users only. All activity is monitored    *
* and logged. Unauthorized access will be prosecuted.                    *
***************************************************************************
EOF

systemctl reload sshd

# ---------------------------------------------
# 5. Firewall (UFW)
# ---------------------------------------------
log "Configuring firewall..."
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
# Allow Docker internal network
ufw allow from 172.17.0.0/16 comment 'Docker network'
ufw --force enable

# ---------------------------------------------
# 6. Fail2Ban
# ---------------------------------------------
log "Configuring Fail2Ban..."
cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3
backend = systemd
destemail = admin@your-domain.com
sendername = Fail2Ban-Server
action = %(action_mwl)s

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 86400

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
port = http,https
logpath = /var/log/nginx/error.log

[nginx-botsearch]
enabled = true
filter = nginx-botsearch
port = http,https
logpath = /var/log/nginx/access.log
maxretry = 2
bantime = 86400
EOF

systemctl enable fail2ban
systemctl restart fail2ban

# ---------------------------------------------
# 7. System Limits & Kernel Parameters
# ---------------------------------------------
log "Configuring system limits..."
cat > /etc/security/limits.d/99-app.conf << 'EOF'
appuser soft nofile 65536
appuser hard nofile 65536
appuser soft nproc 32768
appuser hard nproc 32768
root soft nofile 65536
root hard nofile 65536
EOF

cat > /etc/sysctl.d/99-network-tuning.conf << 'EOF'
# Network tuning for high concurrency
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 65535
net.ipv4.tcp_max_syn_backlog = 65535
net.ipv4.tcp_fin_timeout = 15
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_keepalive_time = 300
net.ipv4.tcp_keepalive_probes = 5
net.ipv4.tcp_keepalive_intvl = 15
net.ipv4.ip_local_port_range = 1024 65535
fs.file-max = 1000000
vm.swappiness = 10
vm.dirty_ratio = 15
vm.dirty_background_ratio = 5
EOF

sysctl --system

# ---------------------------------------------
# 8. Log Rotation
# ---------------------------------------------
log "Configuring log rotation..."
cat > /etc/logrotate.d/ai-marketing-saas << 'EOF'
/var/log/nginx/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        [ -f /var/run/nginx.pid ] && kill -USR1 `cat /var/run/nginx.pid`
    endscript
}

/var/log/supervisor/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 appuser appgroup
}

/opt/ai-marketing-saas/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 appuser appgroup
}
EOF

# ---------------------------------------------
# 9. Application Directory
# ---------------------------------------------
log "Creating application directory..."
mkdir -p /opt/ai-marketing-saas
chown -R appuser:appuser /opt/ai-marketing-saas

# ---------------------------------------------
# 10. SSL Certificate (Let's Encrypt)
# ---------------------------------------------
log "Setting up SSL certificate directory..."
mkdir -p /etc/nginx/ssl
chmod 700 /etc/nginx/ssl

# Note: Run certbot manually after DNS is configured:
# certbot --nginx -d your-domain.com -d www.your-domain.com

# ---------------------------------------------
# 11. Monitoring (Node Exporter for Prometheus)
# ---------------------------------------------
log "Installing Node Exporter..."
wget -q https://github.com/prometheus/node_exporter/releases/download/v1.8.1/node_exporter-1.8.1.linux-amd64.tar.gz
tar xzf node_exporter-1.8.1.linux-amd64.tar.gz
cp node_exporter-1.8.1.linux-amd64/node_exporter /usr/local/bin/
rm -rf node_exporter-1.8.1.linux-amd64*

cat > /etc/systemd/system/node_exporter.service << 'EOF'
[Unit]
Description=Node Exporter
After=network.target

[Service]
User=nobody
Group=nogroup
Type=simple
ExecStart=/usr/local/bin/node_exporter
Restart=on-failure

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable node_exporter
systemctl start node_exporter

# ---------------------------------------------
# 12. Automatic Security Updates
# ---------------------------------------------
log "Configuring unattended upgrades..."
apt-get install -y unattended-upgrades
cat > /etc/apt/apt.conf.d/50unattended-upgrades << 'EOF'
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}-security";
    "${distro_id}:${distro_codename}-updates";
    "origin=Docker,codename=${distro_codename},label=Docker CE";
};
Unattended-Upgrade::Package-Blacklist {
    "docker-ce";
    "docker-ce-cli";
    "containerd.io";
};
Unattended-Upgrade::AutoFixInterruptedDpkg "true";
Unattended-Upgrade::MinimalSteps "true";
Unattended-Upgrade::Remove-Unused-Kernel-Packages "true";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
Unattended-Upgrade::Automatic-Reboot "false";
EOF

cat > /etc/apt/apt.conf.d/20auto-upgrades << 'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::Download-Upgradeable-Packages "1";
APT::Periodic::AutocleanInterval "7";
EOF

# ---------------------------------------------
# 13. Time Synchronization
# ---------------------------------------------
log "Configuring time synchronization..."
timedatectl set-timezone UTC
systemctl enable systemd-timesyncd
systemctl start systemd-timesyncd

# ---------------------------------------------
# 14. Cleanup
# ---------------------------------------------
log "Cleaning up..."
apt-get autoremove -y
apt-get autoclean
rm -rf /var/lib/apt/lists/*
rm -f /root/.bash_history

log "✅ Server setup complete!"
log ""
log "NEXT STEPS:"
log "1. Add your SSH public key to /home/appuser/.ssh/authorized_keys"
log "2. Configure DNS to point to this server"
log "3. Run: certbot --nginx -d your-domain.com"
log "4. Copy .env.production to /opt/ai-marketing-saas/.env"
log "5. Deploy: cd /opt/ai-marketing-saas && docker compose up -d"
log ""
log "SECURITY NOTES:"
log "- SSH root login disabled"
log "- Password authentication disabled (key only)"
log "- UFW firewall active"
log "- Fail2Ban monitoring SSH and nginx"
log "- Automatic security updates enabled"
log "- System limits tuned for high concurrency"