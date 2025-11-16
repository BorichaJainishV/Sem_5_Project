# Developer Onboarding Guide

Welcome to the Mystic Clothing development team! This guide will help you get up and running quickly.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Environment Setup](#environment-setup)
3. [Getting the Code](#getting-the-code)
4. [Database Setup](#database-setup)
5. [Running Locally](#running-locally)
6. [Project Structure](#project-structure)
7. [Development Workflow](#development-workflow)
8. [Testing](#testing)
9. [Common Tasks](#common-tasks)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before you begin, ensure you have the following installed:

### Required Software

- **XAMPP** (Apache + PHP 8.x + MySQL/MariaDB)
  - Download from: https://www.apachefriends.org/
  - Includes Apache web server, PHP, and MySQL
  
- **Git** for version control
  - Download from: https://git-scm.com/downloads
  
- **Composer** for PHP dependency management
  - Download from: https://getcomposer.org/download/
  
- **Code Editor** (recommended: VS Code, PHPStorm)
  - VS Code with PHP extensions
  - PHPStorm (commercial IDE with excellent PHP support)

### Recommended Tools

- **phpMyAdmin** (included with XAMPP) for database management
- **Postman** or **Insomnia** for API testing
- **Git GUI client** (optional): GitHub Desktop, GitKraken, SourceTree

### Knowledge Requirements

- PHP (intermediate level)
- MySQL/SQL basics
- HTML/CSS/JavaScript
- Git version control
- Basic command line usage

---

## Environment Setup

### 1. Install XAMPP

1. Download XAMPP for your operating system
2. Run the installer (choose default components)
3. Install to `C:\xampp` (Windows) or `/Applications/XAMPP` (Mac)
4. Start the XAMPP Control Panel

### 2. Configure PHP

Edit `php.ini` (in XAMPP: `C:\xampp\php\php.ini`):

```ini
# Enable required extensions
extension=mysqli
extension=pdo_mysql
extension=curl
extension=openssl
extension=mbstring
extension=gd

# Increase limits for file uploads
upload_max_filesize = 20M
post_max_size = 20M
max_execution_time = 300
memory_limit = 256M

# Enable error display for development
display_errors = On
error_reporting = E_ALL

# Set timezone
date.timezone = America/New_York
```

Restart Apache after making changes.

### 3. Install Composer

**Windows:**
1. Download and run Composer-Setup.exe
2. Follow installer instructions
3. Verify installation: `composer --version`

**Mac/Linux:**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
composer --version
```

### 4. Configure Git

```bash
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
git config --global core.autocrlf true  # Windows only
```

---

## Getting the Code

### Clone the Repository

```bash
# Navigate to XAMPP's htdocs directory
cd C:\xampp\htdocs  # Windows
cd /Applications/XAMPP/htdocs  # Mac

# Clone the repository
git clone https://github.com/BorichaJainishV/Sem_5_Project.git
cd Sem_5_Project

# Install PHP dependencies
composer install
```

### Project Structure Overview

```
Sem_5_Project/
├── admin/              # Admin panel pages
├── core/               # Business logic modules
├── css/                # Stylesheets
├── database/           # Database schema and migrations
├── docs/               # Documentation (you are here!)
├── emails/             # Email templates
├── image/              # Static images
├── js/                 # JavaScript files
├── partials/           # Reusable PHP components
├── scripts/            # CLI automation scripts
├── storage/            # File-based storage (JSON)
│   └── logs/          # Application logs
├── tests/              # PHPUnit tests
├── .env.example        # Environment variables template
├── composer.json       # PHP dependencies
├── index.php           # Homepage
└── README.md           # Quick start guide
```

---

## Database Setup

### 1. Create Database

Using phpMyAdmin (http://localhost/phpmyadmin):

1. Click "New" to create a database
2. Name it: `mystic_clothing`
3. Collation: `utf8mb4_unicode_ci`
4. Click "Create"

Or via command line:

```bash
mysql -u root -p
CREATE DATABASE mystic_clothing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### 2. Import Schema

**Using phpMyAdmin:**
1. Select `mystic_clothing` database
2. Click "Import" tab
3. Choose file: `database/mystic_clothing.sql`
4. Click "Go"

**Using command line:**
```bash
mysql -u root -p mystic_clothing < database/mystic_clothing.sql
```

### 3. Configure Database Connection

Copy the environment example file:

```bash
cp .env.example .env
```

Edit `.env` with your database credentials:

```env
DB_HOST=127.0.0.1
DB_NAME=mystic_clothing
DB_USER=root
DB_PASS=your_mysql_password
MYSTIC_ENV=local
DROP_SCHEDULER_ALLOW_ACTIVATE=false
PHP_PATH=C:\\xampp\\php\\php.exe
```

**Note:** For security, `.env` is in `.gitignore` and should never be committed.

### 4. Create Admin User

Run this SQL to create an admin account:

```sql
INSERT INTO users (username, email, password, first_name, last_name, role, is_active)
VALUES (
    'admin',
    'admin@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password: "password"
    'Admin',
    'User',
    'admin',
    1
);
```

**Default credentials:**
- Username: `admin`
- Password: `password`

⚠️ **Change this password immediately after first login!**

---

## Running Locally

### 1. Start XAMPP Services

Open XAMPP Control Panel and start:
- Apache (web server)
- MySQL (database)

### 2. Access the Application

Open your browser and navigate to:

- **Storefront:** http://localhost/Sem_5_Project/
- **Admin Panel:** http://localhost/Sem_5_Project/admin/
- **phpMyAdmin:** http://localhost/phpmyadmin

### 3. Alternative: PHP Built-in Server

For quick testing without Apache:

```bash
cd C:\xampp\htdocs\Sem_5_Project
php -S localhost:8000
```

Access at: http://localhost:8000

**Note:** Task scheduler scripts require full XAMPP stack.

---

## Development Workflow

### Branching Strategy

We use **feature branches** for all development:

```bash
# Create a feature branch
git checkout -b feature/my-new-feature

# Make your changes
# ... edit files ...

# Commit changes
git add .
git commit -m "feat: add new feature description"

# Push to remote
git push origin feature/my-new-feature
```

### Commit Message Format

Follow conventional commits:

```
<type>: <description>

[optional body]

[optional footer]
```

**Types:**
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `style:` - Code style changes (formatting)
- `refactor:` - Code refactoring
- `test:` - Adding tests
- `chore:` - Maintenance tasks

**Examples:**
```
feat: add product filtering to shop page
fix: resolve cart total calculation bug
docs: update API documentation
```

### Pull Request Process

1. Create feature branch from `main`
2. Make your changes
3. Write/update tests if applicable
4. Run linting and tests locally
5. Push branch and open Pull Request
6. Request review from team member
7. Address review comments
8. Merge after approval

---

## Testing

### Running Tests

We use PHPUnit for testing:

```bash
# Run all tests
php vendor/bin/phpunit

# Run specific test file
php vendor/bin/phpunit tests/DropWaitlistTest.php

# Run with coverage (requires Xdebug)
php vendor/bin/phpunit --coverage-html coverage/
```

### Writing Tests

Create tests in the `tests/` directory:

```php
<?php
use PHPUnit\Framework\TestCase;

class MyFeatureTest extends TestCase
{
    public function testSomething()
    {
        $result = myFunction();
        $this->assertTrue($result);
    }
}
```

### Linting

Check PHP syntax:

```bash
# Single file
php -l path/to/file.php

# All PHP files
find . -name "*.php" -exec php -l {} \;
```

---

## Common Tasks

### Adding a New Page

1. Create PHP file in root or appropriate directory:
   ```php
   <?php
   require_once 'header.php';
   ?>
   
   <div class="container">
       <h1>My New Page</h1>
       <!-- Your content here -->
   </div>
   
   <?php
   require_once 'footer.php';
   ?>
   ```

2. Add navigation link in `header.php` if needed

3. Create database tables/columns if required

4. Test locally before committing

### Adding a New Admin Page

1. Create file in `admin/` directory
2. Include admin header/footer:
   ```php
   <?php
   require_once '_header.php';
   require_once '_sidebar.php';
   ?>
   
   <div class="main-content">
       <!-- Your admin content -->
   </div>
   
   <?php
   require_once '_footer.php';
   ?>
   ```

3. Add to admin navigation in `admin/_sidebar.php`

### Creating a Database Migration

1. Create SQL file in `database/migrations/`:
   ```
   2024_11_16_120000_add_new_column.sql
   ```

2. Write migration SQL:
   ```sql
   ALTER TABLE products 
   ADD COLUMN new_field VARCHAR(100) DEFAULT NULL;
   ```

3. Test migration:
   ```bash
   mysql -u root -p mystic_clothing < database/migrations/2024_11_16_120000_add_new_column.sql
   ```

4. Document in migration file comments

### Adding a New Scheduled Task

1. Create PHP script in `scripts/`:
   ```php
   <?php
   // Script logic here
   echo "Task completed\n";
   ```

2. Create PowerShell wrapper if needed

3. Test manually:
   ```bash
   php scripts/my_task.php
   ```

4. Document in `docs/ops.md`

5. Register with Task Scheduler (see Operations Guide)

### Debugging

**Enable error logging:**
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

**Log to file:**
```php
error_log("Debug message: " . print_r($variable, true));
```

**View PHP error log:**
- XAMPP: `C:\xampp\apache\logs\error.log`

**MySQL query log:**
```sql
SET GLOBAL general_log = 'ON';
SET GLOBAL log_output = 'TABLE';
SELECT * FROM mysql.general_log;
```

---

## Troubleshooting

### Apache Won't Start

**Problem:** Port 80/443 already in use

**Solutions:**
1. Check for other web servers (IIS, Nginx)
2. Change Apache ports in `httpd.conf`
3. Stop Skype (uses port 80)
4. Check Windows services

### MySQL Won't Start

**Problem:** Port 3306 in use or corrupt data

**Solutions:**
1. Stop other MySQL services
2. Check Task Manager for mysqld.exe
3. Backup and delete `C:\xampp\mysql\data\ib*` files
4. Restart MySQL

### 404 Not Found

**Problem:** Pages not loading

**Solutions:**
1. Check file paths (case-sensitive on Linux)
2. Verify `.htaccess` settings
3. Enable mod_rewrite in Apache
4. Check DocumentRoot in httpd.conf

### Database Connection Failed

**Problem:** Can't connect to MySQL

**Solutions:**
1. Verify credentials in `.env`
2. Check MySQL is running
3. Test connection:
   ```bash
   mysql -u root -p
   ```
4. Check `bind-address` in `my.ini`

### Composer Install Fails

**Problem:** Dependency installation errors

**Solutions:**
1. Update Composer: `composer self-update`
2. Clear cache: `composer clear-cache`
3. Delete `vendor/` and `composer.lock`, reinstall
4. Check PHP extensions enabled

### Session Issues

**Problem:** Login doesn't persist

**Solutions:**
1. Check session save path writeable:
   ```php
   echo session_save_path();
   ```
2. Verify cookies enabled in browser
3. Clear browser cookies/cache
4. Check session.cookie_domain in php.ini

---

## Best Practices

### Code Style

1. **Use PSR-12 coding standards**
2. **Indent with 4 spaces** (not tabs)
3. **Opening braces on same line** for functions/classes
4. **Use meaningful variable names**
5. **Add comments for complex logic**

### Security

1. **Always use prepared statements** for SQL
2. **Escape output** with `htmlspecialchars()`
3. **Validate and sanitize** all user input
4. **Use CSRF tokens** for forms
5. **Never commit credentials** or `.env` files

### Performance

1. **Minimize database queries** in loops
2. **Use indexes** on frequently queried columns
3. **Cache results** when appropriate
4. **Optimize images** before upload
5. **Minify CSS/JS** in production

### Git

1. **Commit often** with clear messages
2. **Pull before push** to avoid conflicts
3. **Review your changes** before committing
4. **Don't commit** generated files or dependencies
5. **Use .gitignore** properly

---

## Getting Help

### Resources

- **Project Documentation:** `/docs/` directory
- **Database Schema:** `docs/DATABASE_SCHEMA.md`
- **API Reference:** `docs/api/API_REFERENCE.md`
- **Architecture:** `docs/ARCHITECTURE.md`

### Team Communication

- **Questions:** Open a GitHub Discussion
- **Bugs:** Create a GitHub Issue
- **Features:** Submit a Pull Request

### External Resources

- PHP Documentation: https://www.php.net/docs.php
- MySQL Reference: https://dev.mysql.com/doc/
- Stack Overflow: https://stackoverflow.com/questions/tagged/php

---

## Next Steps

Now that you're set up:

1. ✅ Explore the codebase
2. ✅ Read the [Architecture Overview](../ARCHITECTURE.md)
3. ✅ Review the [Database Schema](../DATABASE_SCHEMA.md)
4. ✅ Try making a small change
5. ✅ Run the test suite
6. ✅ Pick up your first task!

Welcome aboard! 🚀

---

**Last Updated:** November 16, 2024
