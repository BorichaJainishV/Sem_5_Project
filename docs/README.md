# Documentation Index

Welcome to the Mystic Clothing documentation! This index helps you find the right documentation for your needs.

## 📖 Getting Started

Choose your path:

- **👨‍💻 I'm a Developer** → Start with [Developer Guide](guides/DEVELOPER_GUIDE.md)
- **👔 I'm an Administrator** → Start with [Admin Guide](guides/ADMIN_GUIDE.md)
- **🛍️ I'm a User/Customer** → Start with [User Guide](guides/USER_GUIDE.md)
- **🚀 I'm Deploying** → Start with [Deployment Guide](guides/DEPLOYMENT_GUIDE.md)

---

## 📚 Core Documentation

### System Documentation

| Document | Description | Audience |
|----------|-------------|----------|
| **[Architecture Overview](ARCHITECTURE.md)** | System design, components, and technology stack | Developers, Architects |
| **[Database Schema](DATABASE_SCHEMA.md)** | Complete database structure and relationships | Developers, DBAs |
| **[API Reference](api/API_REFERENCE.md)** | All APIs, endpoints, and CLI commands | Developers, Integrators |

### User Guides

| Document | Description | Audience |
|----------|-------------|----------|
| **[Developer Guide](guides/DEVELOPER_GUIDE.md)** | Setup, workflow, and development practices | Developers |
| **[Deployment Guide](guides/DEPLOYMENT_GUIDE.md)** | Production deployment and configuration | DevOps, System Admins |
| **[User Guide](guides/USER_GUIDE.md)** | Shopping, design tool, and customer features | End Users, Support |
| **[Admin Guide](guides/ADMIN_GUIDE.md)** | Administrative functions and management | Administrators, Managers |

### Operations

| Document | Description | Audience |
|----------|-------------|----------|
| **[Operations Guide](ops.md)** | Scheduler, automation, and maintenance | DevOps, Administrators |
| **[Security Policy](../SECURITY.md)** | Security practices and incident response | All |
| **[Contributing Guide](../CONTRIBUTING.md)** | Contribution workflow and standards | Contributors |

---

## 🔍 Find What You Need

### By Topic

#### Installation & Setup
- [Quick Start](../README.md#quick-start)
- [Developer Environment Setup](guides/DEVELOPER_GUIDE.md#environment-setup)
- [Database Setup](guides/DEVELOPER_GUIDE.md#database-setup)
- [Production Deployment](guides/DEPLOYMENT_GUIDE.md)

#### Development
- [Project Structure](ARCHITECTURE.md#file-structure-overview)
- [Development Workflow](guides/DEVELOPER_GUIDE.md#development-workflow)
- [Testing](guides/DEVELOPER_GUIDE.md#testing)
- [Code Style](guides/DEVELOPER_GUIDE.md#best-practices)

#### Database
- [Schema Overview](DATABASE_SCHEMA.md#core-tables)
- [Table Relationships](DATABASE_SCHEMA.md#relationships-diagram)
- [Migrations](DATABASE_SCHEMA.md#data-migrations)
- [Backup & Restore](DATABASE_SCHEMA.md#backup--restore)

#### APIs & Integration
- [Public APIs](api/API_REFERENCE.md#public-apis)
- [Admin APIs](api/API_REFERENCE.md#admin-apis)
- [CLI Commands](api/API_REFERENCE.md#cli-commands)
- [Error Handling](api/API_REFERENCE.md#error-handling)

#### Features
- [E-commerce](guides/USER_GUIDE.md#shopping)
- [Design Tool](guides/USER_GUIDE.md#custom-design-tool)
- [Drop System](guides/ADMIN_GUIDE.md#marketing--promotions)
- [Style Quiz](guides/USER_GUIDE.md#style-quiz)
- [Spotlight Gallery](guides/ADMIN_GUIDE.md#spotlight-gallery)

#### Administration
- [Dashboard](guides/ADMIN_GUIDE.md#dashboard-overview)
- [Product Management](guides/ADMIN_GUIDE.md#product-management)
- [Order Processing](guides/ADMIN_GUIDE.md#order-management)
- [Customer Management](guides/ADMIN_GUIDE.md#customer-management)
- [Marketing Tools](guides/ADMIN_GUIDE.md#marketing--promotions)

#### Operations
- [Task Scheduler](ops.md#register-task-scheduler-example)
- [Backup Procedures](guides/DEPLOYMENT_GUIDE.md#backup-strategy)
- [Monitoring](guides/DEPLOYMENT_GUIDE.md#monitoring--logging)
- [Troubleshooting](guides/DEVELOPER_GUIDE.md#troubleshooting)

---

## 🎯 Quick Reference

### File Locations

```
docs/
├── ARCHITECTURE.md           # System architecture
├── DATABASE_SCHEMA.md        # Database documentation
├── api/
│   └── API_REFERENCE.md     # API documentation
├── guides/
│   ├── DEVELOPER_GUIDE.md   # Developer setup & workflow
│   ├── DEPLOYMENT_GUIDE.md  # Production deployment
│   ├── USER_GUIDE.md        # End-user features
│   └── ADMIN_GUIDE.md       # Admin functions
├── ops.md                   # Operations & scheduler
└── README.md                # This file
```

### Key Commands

```bash
# Development
composer install              # Install dependencies
php vendor/bin/phpunit       # Run tests
php -l file.php              # Lint PHP file

# Database
mysql -u root -p mystic_clothing < database/mystic_clothing.sql  # Import schema

# Scheduler
php scripts/drop_scheduler.php --dry-run  # Test scheduler

# Backup
mysqldump -u root -p mystic_clothing > backup.sql  # Backup database
```

### Important URLs

- **Storefront:** http://localhost/Sem_5_Project/
- **Admin Panel:** http://localhost/Sem_5_Project/admin/
- **phpMyAdmin:** http://localhost/phpmyadmin
- **Repository:** https://github.com/BorichaJainishV/Sem_5_Project

---

## 📖 Documentation by Role

### For New Developers

1. Read [Architecture Overview](ARCHITECTURE.md)
2. Follow [Developer Guide - Getting Started](guides/DEVELOPER_GUIDE.md#getting-started)
3. Review [Database Schema](DATABASE_SCHEMA.md)
4. Explore [API Reference](api/API_REFERENCE.md)
5. Check [Development Workflow](guides/DEVELOPER_GUIDE.md#development-workflow)

**Estimated Time:** 2-3 hours

### For System Administrators

1. Review [Architecture Overview](ARCHITECTURE.md)
2. Read [Deployment Guide](guides/DEPLOYMENT_GUIDE.md)
3. Study [Operations Guide](ops.md)
4. Review [Security Policy](../SECURITY.md)
5. Set up [Monitoring](guides/DEPLOYMENT_GUIDE.md#monitoring--logging)

**Estimated Time:** 3-4 hours

### For Content Administrators

1. Read [Admin Guide - Getting Started](guides/ADMIN_GUIDE.md#getting-started)
2. Learn [Product Management](guides/ADMIN_GUIDE.md#product-management)
3. Study [Order Processing](guides/ADMIN_GUIDE.md#order-management)
4. Review [Marketing Tools](guides/ADMIN_GUIDE.md#marketing--promotions)
5. Explore [Reports](guides/ADMIN_GUIDE.md#reports--analytics)

**Estimated Time:** 2-3 hours

### For End Users

1. Read [User Guide - Getting Started](guides/USER_GUIDE.md#getting-started)
2. Learn [Shopping](guides/USER_GUIDE.md#shopping)
3. Try [Design Tool](guides/USER_GUIDE.md#custom-design-tool)
4. Take [Style Quiz](guides/USER_GUIDE.md#style-quiz)
5. Explore [Your Account](guides/USER_GUIDE.md#your-account)

**Estimated Time:** 30-45 minutes

---

## 🔧 Advanced Topics

### Architecture & Design

- [System Architecture](ARCHITECTURE.md#high-level-architecture)
- [Application Layers](ARCHITECTURE.md#application-layers)
- [Key Features & Components](ARCHITECTURE.md#key-features--components)
- [Security Architecture](ARCHITECTURE.md#security-architecture)
- [Scalability Considerations](ARCHITECTURE.md#scalability-considerations)

### Database

- [Complete Schema](DATABASE_SCHEMA.md#core-tables)
- [Performance Optimization](DATABASE_SCHEMA.md#performance-optimization)
- [Security Considerations](DATABASE_SCHEMA.md#security-considerations)
- [Maintenance Tasks](DATABASE_SCHEMA.md#maintenance-tasks)

### APIs & Integration

- [Authentication](api/API_REFERENCE.md#authentication)
- [Error Handling](api/API_REFERENCE.md#error-handling)
- [Rate Limiting](api/API_REFERENCE.md#rate-limiting)
- [Webhooks (Planned)](api/API_REFERENCE.md#webhooks-future)

### Operations

- [Task Scheduling](ops.md#register-task-scheduler-example)
- [Emergency Procedures](ops.md#emergency-deactivation)
- [Backup & Restore](ops.md#backups--restore)
- [Monitoring](ops.md#troubleshooting-tips)

---

## 📝 Contributing to Documentation

Found an issue or want to improve documentation?

1. **Report Issues:** Open a GitHub issue with label "documentation"
2. **Suggest Changes:** Submit a pull request with improvements
3. **Ask Questions:** Use GitHub Discussions

### Documentation Standards

- **Clarity:** Write for your audience (technical vs. non-technical)
- **Examples:** Include code samples and commands
- **Structure:** Use clear headings and navigation
- **Accuracy:** Keep documentation in sync with code
- **Links:** Cross-reference related docs

### Writing Guidelines

- Use Markdown format
- Include table of contents for long docs
- Add code blocks with syntax highlighting
- Provide step-by-step instructions
- Include screenshots where helpful
- Keep language simple and clear

---

## 📮 Getting Help

### Documentation Issues

If you can't find what you're looking for:

1. Check the search function in your editor/browser
2. Review related documents using the cross-references
3. Check the [Troubleshooting sections](#by-topic)
4. Open a GitHub issue or discussion

### Technical Support

- **Bugs:** [GitHub Issues](https://github.com/BorichaJainishV/Sem_5_Project/issues)
- **Questions:** [GitHub Discussions](https://github.com/BorichaJainishV/Sem_5_Project/discussions)
- **Security:** See [Security Policy](../SECURITY.md)

---

## 📅 Documentation Updates

| Date | Version | Changes |
|------|---------|---------|
| 2024-11-16 | 1.0 | Initial comprehensive documentation release |

---

## 🎉 Thank You!

Thank you for using Mystic Clothing and reading our documentation. We strive to keep it clear, accurate, and helpful.

**Have feedback on the docs?** We'd love to hear it! Open an issue or start a discussion.

---

**Last Updated:** November 16, 2024  
**Documentation Version:** 1.0
