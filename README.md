<!-- Project README for Mystic Clothing (Sem_5_Project) -->
# Mystic Clothing Platform

<div align="center">

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-Custom-blue)

**A full-featured e-commerce platform for custom apparel design and sales**

[Features](#features) • [Quick Start](#quick-start) • [Documentation](#documentation) • [Contributing](#contributing)

</div>

---

## Overview

Mystic Clothing is a comprehensive e-commerce solution built for a college semester project. It combines traditional online shopping with innovative features like a 3D design tool, scheduled product drops, style quiz recommendations, and automated marketing workflows.

### Key Features

- 🛍️ **Full E-commerce** - Complete storefront with cart, checkout, and order management
- 🎨 **3D Design Tool** - Interactive custom design creator with remix capabilities
- ⏰ **Drop Scheduler** - Automated time-limited product releases with waitlists
- 👗 **Style Quiz** - Personalized product recommendations based on user preferences
- 🎯 **Spotlight Gallery** - Community design showcase with curation tools
- 📊 **Admin Dashboard** - Comprehensive management interface
- 🤖 **Task Automation** - Windows Task Scheduler integration for background jobs
- 📧 **Email Marketing** - Automated customer communications

---

## Quick Start

### Prerequisites

- **XAMPP** (Apache + PHP 8.x + MySQL)
- **Composer** (PHP dependency manager)
- **Git** (version control)

### Installation

1. **Install XAMPP and start services:**
   - Download from [apachefriends.org](https://www.apachefriends.org/)
   - Start Apache and MySQL from XAMPP Control Panel

2. **Clone the repository:**
   ```bash
   cd C:\xampp\htdocs  # Windows
   git clone https://github.com/BorichaJainishV/Sem_5_Project.git
   cd Sem_5_Project
   ```

3. **Install dependencies:**
   ```bash
   composer install
   ```

4. **Import the database:**
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Create database: `mystic_clothing`
   - Import: `database/mystic_clothing.sql`

5. **Configure environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

6. **Access the application:**
   - **Storefront:** http://localhost/Sem_5_Project/
   - **Admin Panel:** http://localhost/Sem_5_Project/admin/
   - **Default Admin:** username: `admin`, password: `password` (change immediately!)

### Alternative: PHP Built-in Server

For quick testing:
```bash
php -S localhost:8000
```
Then open: http://localhost:8000

---

## Technology Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 8.x |
| **Database** | MySQL 5.7+ / MariaDB |
| **Frontend** | HTML5, CSS3, Vanilla JavaScript |
| **Web Server** | Apache (XAMPP) |
| **Dependencies** | Composer, PHPMailer |
| **Testing** | PHPUnit |
| **Automation** | PowerShell, Windows Task Scheduler |

---

## Documentation

### 📚 Complete Guides

| Document | Description |
|----------|-------------|
| **[Architecture Overview](docs/ARCHITECTURE.md)** | System design and technical architecture |
| **[Database Schema](docs/DATABASE_SCHEMA.md)** | Complete database documentation |
| **[API Reference](docs/api/API_REFERENCE.md)** | All available APIs and endpoints |
| **[Developer Guide](docs/guides/DEVELOPER_GUIDE.md)** | Setup and development workflow |
| **[Deployment Guide](docs/guides/DEPLOYMENT_GUIDE.md)** | Production deployment instructions |
| **[User Guide](docs/guides/USER_GUIDE.md)** | Customer-facing features |
| **[Admin Guide](docs/guides/ADMIN_GUIDE.md)** | Administrative functions |
| **[Operations Guide](docs/ops.md)** | Scheduler and maintenance |

### 🔧 Quick References

- **Project Structure:** See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md#file-structure-overview)
- **Database Tables:** See [docs/DATABASE_SCHEMA.md](docs/DATABASE_SCHEMA.md#core-tables)
- **API Endpoints:** See [docs/api/API_REFERENCE.md](docs/api/API_REFERENCE.md#public-apis)
- **CLI Commands:** See [docs/api/API_REFERENCE.md](docs/api/API_REFERENCE.md#cli-commands)

---

## Project Structure

```
Sem_5_Project/
├── admin/                    # Admin panel pages and tools
├── core/                     # Business logic modules
├── css/                      # Stylesheets
├── database/                 # Schema, migrations, and docs
├── docs/                     # Comprehensive documentation
│   ├── api/                 # API reference
│   ├── guides/              # User guides
│   ├── ARCHITECTURE.md      # System architecture
│   └── DATABASE_SCHEMA.md   # Database documentation
├── emails/                   # Email templates
├── image/                    # Static images and assets
├── js/                       # JavaScript modules
├── partials/                 # Reusable PHP components
├── scripts/                  # Automation and CLI scripts
├── storage/                  # File-based storage (JSON, logs)
├── tests/                    # PHPUnit tests
├── .env.example             # Environment configuration template
├── composer.json            # PHP dependencies
├── index.php                # Application entry point
└── README.md                # This file
```

---

## Features in Detail

### 🛒 E-commerce Core

- Product catalog with categories and filters
- Shopping cart with session/user persistence
- Multi-step checkout process
- Order management and tracking
- Customer accounts and order history
- Reward points system

### 🎨 Design Studio

- Interactive 3D t-shirt designer
- Text, graphics, and image support
- Multiple views (front, back, design map)
- Save and load designs
- Design remixing from public gallery
- Submit to Spotlight for curation

### ⏰ Drop System

- Scheduled product releases
- Countdown timers
- Waitlist management
- Email notifications
- Automated activation/deactivation
- Emergency controls

### 👗 Style Quiz

- Personality-based recommendations
- Persona system (style, palette, goal)
- Product matching algorithm
- Email results delivery
- Account integration

### 📊 Admin Tools

- Dashboard with metrics
- Product CRUD operations
- Order processing workflow
- Customer management
- Marketing campaign controls
- Spotlight curation queue
- Support ticket system

---

## Testing & Quality

### Running Tests

```bash
# Run all tests
php vendor/bin/phpunit

# Run specific test file
php vendor/bin/phpunit tests/DropWaitlistTest.php

# With coverage report
php vendor/bin/phpunit --coverage-html coverage/
```

### Linting

```bash
# Check PHP syntax
php -l path/to/file.php

# Lint all PHP files
find . -name "*.php" -exec php -l {} \;
```

### Code Quality

- PSR-12 coding standards
- Prepared statements for SQL (no SQL injection)
- CSRF token protection
- Input validation and sanitization
- Output escaping

---

## Automation & Task Scheduling

### Drop Scheduler

Automated product drop management:

```bash
# Dry run (test without changes)
php scripts/drop_scheduler.php --dry-run

# Force run (bypass checks)
php scripts/drop_scheduler.php --force
```

### Windows Task Scheduler Setup

```powershell
# Register tasks (run as Administrator)
powershell -ExecutionPolicy Bypass -File scripts/setup_drop_tasks.ps1 -TaskPrefix "Mystic"

# Manage tasks
powershell -ExecutionPolicy Bypass -File scripts/manage_drop_tasks.ps1 -Action status
```

See [Operations Guide](docs/ops.md) for detailed scheduler documentation.

---

## Contributing

We welcome contributions! Please follow these steps:

1. **Fork the repository**
2. **Create a feature branch:** `git checkout -b feature/my-feature`
3. **Make your changes**
4. **Run tests:** `php vendor/bin/phpunit`
5. **Lint your code:** `php -l modified-file.php`
6. **Commit with clear message:** `feat: add new feature`
7. **Push to your fork:** `git push origin feature/my-feature`
8. **Open a Pull Request**

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

### Commit Message Format

Follow conventional commits:

```
<type>: <description>

Types: feat, fix, docs, style, refactor, test, chore
```

---

## Security

### Reporting Vulnerabilities

If you discover a security vulnerability:

1. **Do NOT** open a public issue
2. Email: security@example.com (update with real contact)
3. Provide:
   - Description of vulnerability
   - Steps to reproduce
   - Affected files/versions
   - Suggested fix (if any)

See [SECURITY.md](SECURITY.md) for full security policy.

### Emergency Procedures

**Deactivate drops immediately:**

```bash
php scripts/force_deactivate.php --token=YOUR_SECRET_TOKEN
```

**Backup state files:**

```bash
php scripts/archive_drop_state.php
```

---

## Maintenance & Operations

### Backup Procedures

**Database backup:**
```bash
mysqldump -u root -p mystic_clothing > backup_$(date +%Y%m%d).sql
```

**File backup:**
```bash
tar -czf storage_backup.tar.gz storage/
```

### Log Files

- Application logs: `storage/logs/`
- Scheduler logs: `storage/logs/drop_scheduler.log`
- Error logs: Check Apache error.log

### Regular Maintenance

- **Daily:** Check error logs, verify backups
- **Weekly:** Review slow queries, analyze traffic
- **Monthly:** Update dependencies, security audit

See [Operations Guide](docs/ops.md) for comprehensive procedures.

---

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| Can't connect to database | Check credentials in `.env` |
| Apache won't start | Port 80 in use, check for conflicts |
| MySQL won't start | Port 3306 in use, check services |
| Session issues | Check session.save_path permissions |
| Composer errors | Run `composer clear-cache` |

See [Developer Guide](docs/guides/DEVELOPER_GUIDE.md#troubleshooting) for more solutions.

---

## Roadmap

### Current Features (v1.0)
- ✅ Complete e-commerce functionality
- ✅ 3D design tool with remix
- ✅ Drop scheduler automation
- ✅ Style quiz system
- ✅ Admin dashboard
- ✅ Comprehensive documentation

### Planned Features (v2.0)
- [ ] Mobile app (iOS/Android)
- [ ] Advanced analytics dashboard
- [ ] Social media integration
- [ ] Creator profiles and referrals
- [ ] Multi-language support
- [ ] API for third-party integrations

See [TODO_FEATURES.md](TODO_FEATURES.md) for detailed roadmap.

---

## Team & Credits

### Development Team
- **Project Lead:** Jainish Boricha
- **Institution:** College Semester 5 Project

### Technologies Used
- PHP, MySQL, JavaScript, HTML/CSS
- PHPMailer for email
- PHPUnit for testing
- Apache Web Server

### Special Thanks
- XAMPP team for development environment
- PHP community for excellent documentation
- Contributors and testers

---

## License

This project is a college semester project. No formal license has been set.

For open-source licensing, consider adding:
- MIT License (permissive)
- GPL v3 (copyleft)
- Apache 2.0 (patent protection)

Add a `LICENSE` file when ready to open-source.

---

## Contact & Support

### For Users
- **Email:** support@mysticclothing.com (update with real email)
- **FAQ:** See [User Guide](docs/guides/USER_GUIDE.md)

### For Developers
- **Issues:** [GitHub Issues](https://github.com/BorichaJainishV/Sem_5_Project/issues)
- **Discussions:** [GitHub Discussions](https://github.com/BorichaJainishV/Sem_5_Project/discussions)
- **Documentation:** See [docs/](docs/) directory

### Useful Links
- **Repository:** https://github.com/BorichaJainishV/Sem_5_Project
- **Documentation:** [docs/README.md](docs/README.md)
- **Wiki:** (Coming soon)

---

## Acknowledgments

This project was developed as part of the 5th semester coursework, demonstrating:
- Full-stack web development
- Database design and management
- Task automation and scheduling
- Project documentation
- Software engineering best practices

Thank you for checking out Mystic Clothing! 🎉

---

**Last Updated:** November 16, 2024  
**Version:** 1.0 (Pre-release)  
**Status:** ✅ Active Development
