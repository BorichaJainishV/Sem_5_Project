# Troubleshooting Guide

This guide provides solutions to common issues across all areas of the Mystic Clothing platform.

## Table of Contents

- [Installation Issues](#installation-issues)
- [Server & Environment](#server--environment)
- [Database Problems](#database-problems)
- [Application Errors](#application-errors)
- [Authentication Issues](#authentication-issues)
- [Performance Issues](#performance-issues)
- [Task Scheduler Problems](#task-scheduler-problems)
- [Email Issues](#email-issues)
- [File Upload Problems](#file-upload-problems)
- [Browser & Frontend Issues](#browser--frontend-issues)

---

## Installation Issues

### Composer Install Fails

**Problem:** `composer install` throws errors

**Solutions:**

1. **Update Composer:**
   ```bash
   composer self-update
   ```

2. **Clear cache:**
   ```bash
   composer clear-cache
   composer install
   ```

3. **Check PHP version:**
   ```bash
   php -v  # Should be 8.0+
   ```

4. **Remove and reinstall:**
   ```bash
   rm -rf vendor/ composer.lock
   composer install
   ```

### Database Import Fails

**Problem:** Cannot import `mystic_clothing.sql`

**Solutions:**

1. **Check MySQL is running:**
   ```bash
   # Windows (XAMPP)
   Check XAMPP Control Panel
   
   # Linux
   sudo systemctl status mysql
   ```

2. **Increase import limits:**
   Edit `php.ini`:
   ```ini
   upload_max_filesize = 100M
   post_max_size = 100M
   max_execution_time = 300
   ```

3. **Import via command line:**
   ```bash
   mysql -u root -p mystic_clothing < database/mystic_clothing.sql
   ```

4. **Split large file:**
   ```bash
   # Use mysqlsplit or split command
   split -l 1000 mystic_clothing.sql mystic_part_
   ```

---

## Server & Environment

### Apache Won't Start

**Problem:** Apache fails to start in XAMPP

**Causes & Solutions:**

1. **Port 80 in use:**
   - Close Skype (uses port 80)
   - Stop IIS if running
   - Check other web servers
   
   **Change Apache port:**
   Edit `httpd.conf`:
   ```apache
   Listen 8080
   ServerName localhost:8080
   ```

2. **Missing Visual C++ Redistributable:**
   - Download and install from Microsoft
   - Restart computer

3. **Firewall blocking:**
   - Add Apache to firewall exceptions
   - Temporarily disable to test

### MySQL Won't Start

**Problem:** MySQL service fails to start

**Solutions:**

1. **Port 3306 in use:**
   ```bash
   # Windows
   netstat -ano | findstr :3306
   
   # Linux
   sudo lsof -i :3306
   ```
   
   Kill conflicting process or change port.

2. **Corrupt InnoDB files:**
   ```bash
   # Backup data directory first
   # Delete these files in XAMPP/mysql/data/:
   # ib_logfile0, ib_logfile1
   # Restart MySQL
   ```

3. **Check MySQL error log:**
   ```bash
   # XAMPP
   C:\xampp\mysql\data\mysql_error.log
   ```

### PHP Extensions Missing

**Problem:** Required PHP extension not loaded

**Solution:**

Edit `php.ini`:
```ini
extension=mysqli
extension=pdo_mysql
extension=curl
extension=gd
extension=mbstring
```

Restart Apache after changes.

---

## Database Problems

### Cannot Connect to Database

**Problem:** "Database connection failed"

**Diagnostic Steps:**

1. **Test connection:**
   ```bash
   mysql -u root -p
   # If this fails, MySQL isn't running
   ```

2. **Check credentials:**
   Verify `.env` file:
   ```env
   DB_HOST=127.0.0.1
   DB_NAME=mystic_clothing
   DB_USER=root
   DB_PASS=your_password
   ```

3. **Check MySQL user permissions:**
   ```sql
   SHOW GRANTS FOR 'root'@'localhost';
   ```

4. **Test from PHP:**
   Create `test_db.php`:
   ```php
   <?php
   try {
       $pdo = new PDO(
           "mysql:host=127.0.0.1;dbname=mystic_clothing",
           "root",
           "password"
       );
       echo "Connected successfully!";
   } catch (PDOException $e) {
       echo "Connection failed: " . $e->getMessage();
   }
   ```

### Slow Database Queries

**Problem:** Pages loading slowly due to database

**Solutions:**

1. **Enable slow query log:**
   ```sql
   SET GLOBAL slow_query_log = 'ON';
   SET GLOBAL long_query_time = 2;
   ```

2. **Check missing indexes:**
   ```sql
   EXPLAIN SELECT * FROM orders WHERE user_id = 123;
   # Look for "type: ALL" (full table scan)
   ```

3. **Add indexes:**
   ```sql
   CREATE INDEX idx_user_id ON orders(user_id);
   ```

4. **Optimize tables:**
   ```sql
   OPTIMIZE TABLE orders, products, cart;
   ```

---

## Application Errors

### White Screen of Death

**Problem:** Blank white page, no errors

**Solutions:**

1. **Enable error display:**
   Add to top of problem page:
   ```php
   <?php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

2. **Check PHP error log:**
   ```bash
   # XAMPP
   C:\xampp\apache\logs\error.log
   ```

3. **Check file syntax:**
   ```bash
   php -l problematic_file.php
   ```

4. **Memory limit:**
   Increase in `php.ini`:
   ```ini
   memory_limit = 256M
   ```

### 404 Not Found

**Problem:** Pages return 404 errors

**Solutions:**

1. **Check file exists:**
   Verify file path and name (case-sensitive on Linux)

2. **Check .htaccess:**
   Ensure mod_rewrite is enabled:
   ```apache
   # In httpd.conf
   LoadModule rewrite_module modules/mod_rewrite.so
   ```

3. **Check AllowOverride:**
   ```apache
   <Directory "C:/xampp/htdocs">
       AllowOverride All
   </Directory>
   ```

4. **Test without .htaccess:**
   Temporarily rename `.htaccess` to test

### 500 Internal Server Error

**Problem:** Server error on certain pages

**Solutions:**

1. **Check Apache error log:**
   ```bash
   tail -f /var/log/apache2/error.log
   ```

2. **PHP syntax error:**
   ```bash
   php -l file.php
   ```

3. **Permission issues:**
   ```bash
   # Set proper permissions
   chmod 755 directories
   chmod 644 files
   ```

4. **Check .htaccess syntax:**
   ```bash
   apache2ctl configtest
   ```

---

## Authentication Issues

### Cannot Log In

**Problem:** Valid credentials rejected

**Solutions:**

1. **Check password hash:**
   ```sql
   SELECT username, password FROM users WHERE username = 'admin';
   ```

2. **Reset password:**
   ```sql
   UPDATE users 
   SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
   WHERE username = 'admin';
   -- Password is now: "password"
   ```

3. **Check session settings:**
   ```php
   <?php
   echo "Session save path: " . session_save_path();
   // Ensure this directory exists and is writable
   ```

4. **Clear browser cookies:**
   Delete all cookies for localhost

### Session Not Persisting

**Problem:** Logged out immediately after login

**Solutions:**

1. **Check session save path permissions:**
   ```bash
   # Make writable
   chmod 777 /tmp  # or session.save_path
   ```

2. **Check php.ini:**
   ```ini
   session.save_path = "/tmp"
   session.gc_maxlifetime = 1440
   ```

3. **Verify session_start():**
   Ensure called before any output:
   ```php
   <?php
   session_start();
   // No output before this
   ```

4. **Check for session conflicts:**
   ```php
   <?php
   var_dump($_SESSION);
   // Check what's actually stored
   ```

---

## Performance Issues

### Slow Page Load

**Problem:** Pages take too long to load

**Diagnostic Steps:**

1. **Enable Xdebug profiler:**
   ```ini
   xdebug.mode = profile
   xdebug.output_dir = /tmp/xdebug
   ```

2. **Check database queries:**
   - Enable slow query log
   - Use EXPLAIN on slow queries
   - Add indexes

3. **Profile PHP code:**
   ```php
   $start = microtime(true);
   // Your code here
   echo "Time: " . (microtime(true) - $start) . "s";
   ```

**Solutions:**

1. **Enable OPcache:**
   ```ini
   opcache.enable=1
   opcache.memory_consumption=128
   ```

2. **Optimize images:**
   - Compress images before upload
   - Use appropriate formats (WebP, JPEG)

3. **Reduce queries:**
   - Use JOINs instead of multiple queries
   - Implement caching

### High Memory Usage

**Problem:** PHP memory limit exceeded

**Solutions:**

1. **Increase limit:**
   ```ini
   memory_limit = 512M
   ```

2. **Find memory leaks:**
   ```php
   echo memory_get_usage() . "\n";
   // Your code
   echo memory_get_usage() . "\n";
   ```

3. **Optimize queries:**
   - Use LIMIT for large result sets
   - Fetch only needed columns

---

## Task Scheduler Problems

### Scheduler Not Running

**Problem:** Scheduled tasks don't execute

**Windows Solutions:**

1. **Check Task Scheduler:**
   - Open Task Scheduler
   - Find Mystic tasks
   - Check "Last Run Result"

2. **Verify task settings:**
   - "Run whether user is logged on or not"
   - "Run with highest privileges"
   - Correct path to php.exe

3. **Test manually:**
   ```powershell
   php C:\xampp\htdocs\Sem_5_Project\scripts\drop_scheduler.php
   ```

4. **Check permissions:**
   - Task user has access to PHP
   - Can read/write storage files

**Linux Solutions:**

1. **Check crontab:**
   ```bash
   sudo crontab -l -u www-data
   ```

2. **Check cron logs:**
   ```bash
   grep CRON /var/log/syslog
   ```

3. **Test command:**
   ```bash
   sudo -u www-data php /var/www/mystic-clothing/scripts/drop_scheduler.php
   ```

### Scheduler Runs But Fails

**Problem:** Task executes but produces errors

**Solutions:**

1. **Check logs:**
   ```bash
   tail -f storage/logs/drop_scheduler.log
   ```

2. **Enable verbose mode:**
   ```bash
   php scripts/drop_scheduler.php --verbose
   ```

3. **Check file permissions:**
   ```bash
   ls -la storage/
   # Should be writable by web server user
   ```

4. **Verify environment:**
   ```bash
   php -r "print_r(get_defined_constants(true)['user']);"
   ```

---

## Email Issues

### Emails Not Sending

**Problem:** Email notifications not delivered

**Solutions:**

1. **Test SMTP connection:**
   ```php
   <?php
   require 'vendor/autoload.php';
   use PHPMailer\PHPMailer\PHPMailer;
   
   $mail = new PHPMailer(true);
   $mail->SMTPDebug = 2; // Verbose debug
   $mail->isSMTP();
   $mail->Host = 'smtp.gmail.com';
   $mail->Port = 587;
   $mail->SMTPAuth = true;
   $mail->Username = 'your-email@gmail.com';
   $mail->Password = 'your-app-password';
   
   try {
       $mail->send();
       echo "Success!";
   } catch (Exception $e) {
       echo "Error: " . $mail->ErrorInfo;
   }
   ```

2. **Check SMTP credentials:**
   - Verify username/password in `.env`
   - Use app password for Gmail

3. **Check firewall:**
   - Allow outbound SMTP (port 587/465)

4. **Check spam folder:**
   - Emails may be flagged as spam

### Email Formatting Issues

**Problem:** Emails display incorrectly

**Solutions:**

1. **Use HTML templates:**
   ```php
   $mail->isHTML(true);
   $mail->Body = $htmlContent;
   $mail->AltBody = strip_tags($htmlContent);
   ```

2. **Inline CSS:**
   ```html
   <p style="color: #333;">Text</p>
   ```

3. **Test with email testing tools:**
   - Litmus
   - Email on Acid
   - Mail Tester

---

## File Upload Problems

### Upload Fails

**Problem:** File uploads don't work

**Solutions:**

1. **Check PHP settings:**
   ```ini
   upload_max_filesize = 20M
   post_max_size = 20M
   max_file_uploads = 20
   ```

2. **Check directory permissions:**
   ```bash
   chmod 777 vendor/uploads/
   chmod 777 storage/
   ```

3. **Check form encoding:**
   ```html
   <form enctype="multipart/form-data">
   ```

4. **Check file size:**
   ```php
   echo "Max upload size: " . ini_get('upload_max_filesize');
   ```

### Upload Directory Not Writable

**Problem:** Permission denied errors

**Solutions:**

```bash
# Linux
sudo chown -R www-data:www-data vendor/uploads/
sudo chmod -R 775 vendor/uploads/

# Windows (XAMPP)
# Right-click folder → Properties → Security
# Give "Everyone" or "Users" write permission
```

---

## Browser & Frontend Issues

### JavaScript Not Working

**Problem:** Interactive features broken

**Solutions:**

1. **Check browser console:**
   - Press F12
   - Look for JavaScript errors
   - Fix reported issues

2. **Check file paths:**
   ```html
   <!-- Verify paths are correct -->
   <script src="/js/app.js"></script>
   ```

3. **Check jQuery loaded:**
   ```javascript
   console.log(jQuery);
   // Should show jQuery object
   ```

4. **Clear browser cache:**
   - Hard reload: Ctrl+Shift+R
   - Clear cache in settings

### CSS Not Loading

**Problem:** Styling missing or broken

**Solutions:**

1. **Check file exists:**
   - Verify CSS file path
   - Check browser Network tab

2. **Check MIME type:**
   ```apache
   AddType text/css .css
   ```

3. **Clear cache:**
   - Hard reload page
   - Check "Disable cache" in DevTools

4. **Check for syntax errors:**
   - Use CSS validator
   - Check browser console

### Design Tool Not Saving

**Problem:** Custom designs won't save

**Solutions:**

1. **Check JavaScript errors:**
   - Open browser console
   - Look for errors

2. **Check AJAX call:**
   ```javascript
   // In browser console
   console.log('Saving design...');
   ```

3. **Check server response:**
   - Network tab in DevTools
   - Look for 200 OK status

4. **Check file permissions:**
   ```bash
   chmod 775 storage/designs/
   ```

---

## Getting More Help

If problems persist:

1. **Check Logs:**
   - Apache: `apache/logs/error.log`
   - PHP: Check `php.ini` for error_log location
   - Application: `storage/logs/`

2. **Enable Debug Mode:**
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

3. **Search Documentation:**
   - [Developer Guide](guides/DEVELOPER_GUIDE.md#troubleshooting)
   - [Deployment Guide](guides/DEPLOYMENT_GUIDE.md#troubleshooting)
   - [Operations Guide](ops.md#troubleshooting-tips)

4. **Get Support:**
   - GitHub Issues
   - Stack Overflow
   - PHP.net documentation

---

**Last Updated:** November 16, 2024
