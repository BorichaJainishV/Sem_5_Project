# Deployment Guide

This guide covers deploying Mystic Clothing to production environments.

## Table of Contents

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Server Requirements](#server-requirements)
3. [Deployment Steps](#deployment-steps)
4. [Environment Configuration](#environment-configuration)
5. [Database Migration](#database-migration)
6. [Task Scheduler Setup](#task-scheduler-setup)
7. [Security Hardening](#security-hardening)
8. [Monitoring & Logging](#monitoring--logging)
9. [Rollback Procedures](#rollback-procedures)
10. [Post-Deployment](#post-deployment)

---

## Pre-Deployment Checklist

Before deploying to production, ensure:

- [ ] All tests pass locally
- [ ] Code reviewed and approved
- [ ] Database migrations tested
- [ ] Environment variables documented
- [ ] Backup procedures verified
- [ ] Rollback plan prepared
- [ ] Team notified of deployment window
- [ ] Maintenance page ready (if needed)

---

## Server Requirements

### Minimum Requirements

- **Operating System:** Windows Server 2016+ or Linux (Ubuntu 20.04+, CentOS 7+)
- **Web Server:** Apache 2.4+ or Nginx 1.18+
- **PHP:** 8.0 or higher
- **Database:** MySQL 5.7+ or MariaDB 10.3+
- **Memory:** 2GB RAM minimum, 4GB recommended
- **Storage:** 10GB minimum, 50GB+ recommended for production
- **SSL Certificate:** Required for production (Let's Encrypt or commercial)

### Required PHP Extensions

```bash
php -m | grep -E '(mysqli|pdo_mysql|curl|openssl|mbstring|gd|json|zip)'
```

Required extensions:
- mysqli
- pdo_mysql
- curl
- openssl
- mbstring
- gd
- json
- zip

### Apache Modules

```bash
apache2ctl -M | grep -E '(rewrite|headers|ssl)'
```

Required modules:
- mod_rewrite
- mod_headers
- mod_ssl (for HTTPS)

---

## Deployment Steps

### Option 1: Manual Deployment

#### 1. Prepare the Server

**Install Apache, PHP, MySQL:**

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install apache2 php8.1 mysql-server php8.1-mysql php8.1-curl php8.1-gd php8.1-mbstring php8.1-xml php8.1-zip

# CentOS/RHEL
sudo yum install httpd php php-mysql php-curl php-gd php-mbstring php-xml php-zip
```

#### 2. Configure Web Server

**Apache Virtual Host:**

Create `/etc/apache2/sites-available/mystic-clothing.conf`:

```apache
<VirtualHost *:80>
    ServerName mysticclothing.com
    ServerAlias www.mysticclothing.com
    DocumentRoot /var/www/mystic-clothing
    
    <Directory /var/www/mystic-clothing>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/mystic-error.log
    CustomLog ${APACHE_LOG_DIR}/mystic-access.log combined
    
    # Redirect to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName mysticclothing.com
    ServerAlias www.mysticclothing.com
    DocumentRoot /var/www/mystic-clothing
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/mystic-clothing.crt
    SSLCertificateKeyFile /etc/ssl/private/mystic-clothing.key
    SSLCertificateChainFile /etc/ssl/certs/mystic-clothing-chain.crt
    
    <Directory /var/www/mystic-clothing>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/mystic-error-ssl.log
    CustomLog ${APACHE_LOG_DIR}/mystic-access-ssl.log combined
</VirtualHost>
```

Enable site and modules:

```bash
sudo a2ensite mystic-clothing
sudo a2enmod rewrite ssl headers
sudo systemctl restart apache2
```

#### 3. Deploy Application Code

```bash
# Create application directory
sudo mkdir -p /var/www/mystic-clothing
cd /var/www/mystic-clothing

# Clone repository (or upload files via FTP/SFTP)
sudo git clone https://github.com/BorichaJainishV/Sem_5_Project.git .

# Install dependencies
sudo composer install --no-dev --optimize-autoloader

# Set permissions
sudo chown -R www-data:www-data /var/www/mystic-clothing
sudo chmod -R 755 /var/www/mystic-clothing
sudo chmod -R 775 storage/
sudo chmod -R 775 vendor/uploads/
```

#### 4. Configure Environment

```bash
# Copy environment file
sudo cp .env.example .env
sudo nano .env
```

Edit `.env` with production values:

```env
DB_HOST=localhost
DB_NAME=mystic_clothing_prod
DB_USER=mystic_app_user
DB_PASS=secure_random_password_here

MYSTIC_ENV=production
DROP_SCHEDULER_ALLOW_ACTIVATE=true

PHP_PATH=/usr/bin/php

# Email Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_FROM=noreply@mysticclothing.com

# Security Tokens
DROP_FORCE_DEACTIVATE_TOKEN=generate_random_secure_token_here
CSRF_SECRET=generate_another_random_token_here

# Webhooks (optional)
WEBHOOK_URL=https://hooks.example.com/mystic
WEBHOOK_AUTH_HEADER=Authorization
WEBHOOK_AUTH_VALUE=Bearer your_webhook_token
```

**Generate secure tokens:**

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

Set proper permissions:

```bash
sudo chmod 600 .env
sudo chown www-data:www-data .env
```

---

## Environment Configuration

### PHP Configuration (Production)

Edit `/etc/php/8.1/apache2/php.ini`:

```ini
# Disable error display
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log

# Set limits
upload_max_filesize = 20M
post_max_size = 20M
max_execution_time = 60
memory_limit = 256M

# Security
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off

# Session
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1

# Timezone
date.timezone = America/New_York
```

Create PHP log directory:

```bash
sudo mkdir -p /var/log/php
sudo chown www-data:www-data /var/log/php
```

Restart Apache:

```bash
sudo systemctl restart apache2
```

---

## Database Migration

### 1. Create Production Database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE mystic_clothing_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'mystic_app_user'@'localhost' IDENTIFIED BY 'secure_random_password';

GRANT SELECT, INSERT, UPDATE, DELETE ON mystic_clothing_prod.* TO 'mystic_app_user'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

### 2. Import Schema

```bash
mysql -u mystic_app_user -p mystic_clothing_prod < database/mystic_clothing.sql
```

### 3. Run Migrations

```bash
for migration in database/migrations/*.sql; do
    echo "Running $migration..."
    mysql -u mystic_app_user -p mystic_clothing_prod < "$migration"
done
```

### 4. Verify Database

```bash
mysql -u mystic_app_user -p mystic_clothing_prod -e "SHOW TABLES;"
```

---

## Task Scheduler Setup

### Linux (Cron Jobs)

Edit crontab:

```bash
sudo crontab -e -u www-data
```

Add scheduled tasks:

```cron
# Drop Scheduler - runs every 5 minutes
*/5 * * * * /usr/bin/php /var/www/mystic-clothing/scripts/drop_scheduler.php >> /var/www/mystic-clothing/storage/logs/drop_scheduler.log 2>&1

# Waitlist Conversion Report - daily at 2 AM
0 2 * * * /usr/bin/php /var/www/mystic-clothing/scripts/waitlist_conversion_report.php --output=/var/www/mystic-clothing/storage/reports/waitlist_$(date +\%Y\%m\%d).csv

# Database Backup - daily at 3 AM
0 3 * * * /usr/local/bin/backup_database.sh

# Cleanup old logs - weekly on Sunday at 4 AM
0 4 * * 0 find /var/www/mystic-clothing/storage/logs -name "*.log" -mtime +30 -delete
```

### Windows (Task Scheduler)

Use PowerShell scripts from `scripts/` directory:

```powershell
# Run as Administrator
powershell -ExecutionPolicy Bypass -File scripts/setup_drop_tasks.ps1 -TaskPrefix "MysticProd"
```

See `docs/ops.md` for detailed Windows setup.

---

## Security Hardening

### 1. File Permissions

```bash
# Application files: read-only for web server
sudo find /var/www/mystic-clothing -type f -exec chmod 644 {} \;
sudo find /var/www/mystic-clothing -type d -exec chmod 755 {} \;

# Writable directories
sudo chmod -R 775 storage/
sudo chmod -R 775 vendor/uploads/

# Sensitive files
sudo chmod 600 .env
sudo chmod 600 database/mystic_clothing.sql
```

### 2. Hide Sensitive Files

Add to `.htaccess`:

```apache
# Deny access to sensitive files
<FilesMatch "^\.env|^composer\.(json|lock)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Deny access to storage directory
RedirectMatch 403 ^/storage/

# Protect database files
RedirectMatch 403 ^/database/
```

### 3. SSL/TLS Configuration

**Obtain SSL Certificate (Let's Encrypt):**

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d mysticclothing.com -d www.mysticclothing.com
```

**Test SSL Configuration:**

Visit: https://www.ssllabs.com/ssltest/

### 4. Security Headers

Add to Apache config or `.htaccess`:

```apache
# Security Headers
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';"
```

### 5. Database Security

```sql
# Remove test database
DROP DATABASE IF EXISTS test;

# Disable remote root login
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

# Set secure passwords
ALTER USER 'root'@'localhost' IDENTIFIED BY 'very_secure_root_password';

FLUSH PRIVILEGES;
```

### 6. Firewall Configuration

```bash
# Ubuntu/Debian (UFW)
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable

# CentOS/RHEL (firewalld)
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --reload
```

---

## Monitoring & Logging

### 1. Application Logs

Configure log rotation for `storage/logs/`:

Create `/etc/logrotate.d/mystic-clothing`:

```
/var/www/mystic-clothing/storage/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0664 www-data www-data
    sharedscripts
    postrotate
        systemctl reload apache2 > /dev/null 2>&1
    endscript
}
```

### 2. Server Monitoring

**Install monitoring tools:**

```bash
# Netdata (real-time monitoring)
bash <(curl -Ss https://my-netdata.io/kickstart.sh)

# Access at: http://server-ip:19999
```

### 3. Uptime Monitoring

Use external services:
- UptimeRobot: https://uptimerobot.com/
- Pingdom: https://www.pingdom.com/
- StatusCake: https://www.statuscake.com/

### 4. Error Tracking

Consider integrating:
- Sentry: https://sentry.io/
- Rollbar: https://rollbar.com/
- Bugsnag: https://www.bugsnag.com/

---

## Rollback Procedures

### Quick Rollback

If deployment fails, revert to previous version:

```bash
# Using Git
cd /var/www/mystic-clothing
sudo -u www-data git log --oneline -5  # Find previous commit
sudo -u www-data git checkout <previous-commit-hash>
sudo systemctl restart apache2
```

### Database Rollback

```bash
# Restore from backup
mysql -u mystic_app_user -p mystic_clothing_prod < /backups/db_backup_YYYYMMDD.sql
```

### Full System Rollback

1. Stop web server
2. Restore code from backup
3. Restore database from backup
4. Restart services
5. Verify functionality

---

## Post-Deployment

### 1. Verification Checklist

- [ ] Homepage loads correctly
- [ ] Admin panel accessible
- [ ] Login/logout works
- [ ] Product pages display
- [ ] Cart functionality works
- [ ] Checkout process completes
- [ ] Email notifications sent
- [ ] Scheduled tasks running
- [ ] SSL certificate valid
- [ ] No PHP errors in logs

### 2. Performance Testing

```bash
# Apache Bench (basic load test)
ab -n 1000 -c 10 https://mysticclothing.com/

# Or use more advanced tools:
# - JMeter
# - Gatling
# - k6
```

### 3. Health Checks

Create monitoring endpoint: `health.php`

```php
<?php
$health = [
    'status' => 'ok',
    'timestamp' => time(),
    'database' => false,
    'disk_space' => false
];

// Check database
try {
    $db = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}", 
                  $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $health['database'] = true;
} catch (Exception $e) {
    $health['status'] = 'error';
}

// Check disk space
$free = disk_free_space('/');
$total = disk_total_space('/');
$health['disk_space'] = ($free / $total) > 0.1; // More than 10% free

header('Content-Type: application/json');
echo json_encode($health);
```

### 4. Update Documentation

- [ ] Update CHANGELOG.md
- [ ] Tag release in Git
- [ ] Update deployment notes
- [ ] Document any manual steps
- [ ] Update team wiki/docs

---

## Troubleshooting

### Service Won't Start

```bash
# Check logs
sudo tail -f /var/log/apache2/error.log
sudo tail -f /var/www/mystic-clothing/storage/logs/*.log

# Test configuration
sudo apache2ctl configtest

# Check file permissions
ls -la /var/www/mystic-clothing
```

### Database Connection Issues

```bash
# Test connection
mysql -u mystic_app_user -p mystic_clothing_prod

# Check MySQL status
sudo systemctl status mysql

# Review MySQL logs
sudo tail -f /var/log/mysql/error.log
```

### Performance Issues

```bash
# Check server resources
htop
df -h
free -m

# Enable slow query log
mysql -u root -p -e "SET GLOBAL slow_query_log = 'ON';"
mysql -u root -p -e "SET GLOBAL long_query_time = 2;"
```

---

## Continuous Deployment (Optional)

For automated deployments, consider:

### Using GitHub Actions

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Deploy to server
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /var/www/mystic-clothing
            git pull origin main
            composer install --no-dev
            sudo systemctl restart apache2
```

---

## Backup Strategy

### Automated Backups

Create `/usr/local/bin/backup_database.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="mystic_clothing_prod"

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u mystic_app_user -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Files backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/mystic-clothing/storage /var/www/mystic-clothing/vendor/uploads

# Keep only last 30 days
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +30 -delete
find $BACKUP_DIR -name "files_*.tar.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

Make executable:

```bash
sudo chmod +x /usr/local/bin/backup_database.sh
```

---

## Support & Maintenance

### Regular Maintenance Tasks

**Daily:**
- Check error logs
- Verify backups completed
- Monitor disk space

**Weekly:**
- Review slow query log
- Check security updates
- Analyze traffic patterns

**Monthly:**
- Update dependencies
- Review and rotate logs
- Database optimization
- Security audit

---

**For additional support, see:**
- [Architecture Documentation](ARCHITECTURE.md)
- [Operations Guide](ops.md)
- [Security Policy](../SECURITY.md)

---

**Last Updated:** November 16, 2024
