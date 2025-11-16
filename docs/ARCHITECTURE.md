# Mystic Clothing - Architecture Overview

## System Architecture

Mystic Clothing is a full-stack e-commerce platform built with PHP, MySQL, and vanilla JavaScript. The system follows a traditional server-rendered architecture with modern interactive components.

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Client Layer                          │
│  (Web Browser - HTML/CSS/JavaScript)                        │
└─────────────────────────────────────────────────────────────┘
                            ↓ HTTP/HTTPS
┌─────────────────────────────────────────────────────────────┐
│                     Web Server Layer                         │
│  (Apache/XAMPP - PHP 8.x Runtime)                           │
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │  Storefront  │  │  Admin Panel │  │  API Handlers│     │
│  │  Pages       │  │              │  │              │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                    Data Persistence Layer                    │
│                                                              │
│  ┌──────────────────────┐  ┌──────────────────────┐        │
│  │  MySQL Database      │  │  JSON File Storage   │        │
│  │  (Relational Data)   │  │  (State Files)       │        │
│  └──────────────────────┘  └──────────────────────┘        │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                  Background Services Layer                   │
│                                                              │
│  ┌─────────────────┐  ┌─────────────────┐                 │
│  │ Drop Scheduler  │  │ Task Scheduler  │                 │
│  │ (PHP CLI)       │  │ (Windows)       │                 │
│  └─────────────────┘  └─────────────────┘                 │
└─────────────────────────────────────────────────────────────┘
```

## Technology Stack

### Backend
- **PHP 8.x**: Server-side scripting language
- **MySQL/MariaDB**: Relational database management system
- **PHPMailer**: Email sending library
- **Composer**: PHP dependency management

### Frontend
- **HTML5**: Markup language
- **CSS3**: Styling (with custom stylesheets)
- **Vanilla JavaScript**: Client-side interactivity
- **No Framework**: Direct DOM manipulation

### DevOps & Automation
- **XAMPP**: Local development environment (Apache + PHP + MySQL)
- **Windows Task Scheduler**: Automated job scheduling
- **PowerShell**: Automation scripting
- **PHPUnit**: Unit testing framework

## Application Layers

### 1. Presentation Layer (Frontend)

#### Storefront Pages
- `index.php` - Homepage with featured products
- `shop.php` - Product catalog with filtering
- `cart.php` - Shopping cart management
- `checkout.php` - Order placement and payment
- `account.php` - User account dashboard
- `design3d.php` - Custom 3D design tool
- `about.php`, `contact.php`, `faq.php` - Information pages

#### Admin Pages (`admin/`)
- `dashboard.php` - Admin overview and metrics
- `products.php` - Product catalog management
- `orders.php` - Order processing and fulfillment
- `customers.php` - Customer management
- `marketing.php` - Promotional campaigns
- `spotlight.php` - Featured design curation
- `support_queue.php` - Customer support tickets

### 2. Business Logic Layer

#### Core Modules (`core/`)
Contains reusable business logic components:
- Banner management
- Drop scheduling
- Waitlist handling
- Style quiz logic
- Session management

#### Handler Scripts (`*_handler.php`)
Process form submissions and API requests:
- `cart_handler.php` - Cart operations
- `email_handler.php` - Email sending
- `login_handler.php` - Authentication
- `style_quiz_handler.php` - Quiz processing
- `support_issue_handler.php` - Support ticket creation

### 3. Data Access Layer

#### Database Schema
Main tables:
- `users` - Customer accounts
- `products` - Product catalog
- `orders` - Order records
- `order_items` - Line items in orders
- `cart` - Shopping cart items
- `designs` - Custom user designs
- `support_tickets` - Customer support
- `waitlists` - Drop waitlist enrollments

#### File-Based Storage (`storage/`)
JSON files for lightweight state:
- `drop_promotions_state.json` - Active promotions
- `drop_waitlists.json` - Waitlist enrollments
- `support_tickets.json` - Support queue
- `logs/` - Application logs

### 4. Background Services Layer

#### Scheduled Tasks (`scripts/`)
Automated processes:
- `drop_scheduler.php` - Manages product drop lifecycles
- `drop_promotion_sync.php` - Syncs promotion state
- `waitlist_conversion_report.php` - Generates analytics
- `scheduler_watchdog.ps1` - Monitors scheduler health
- `auto_revert_failsafe.ps1` - Emergency rollback

## Key Features & Components

### 1. Product Drop System
Automated time-limited product releases:
- Scheduled activation/deactivation
- Countdown timer display
- Waitlist management
- Email notifications
- Emergency deactivation controls

### 2. Custom Design Tool
Interactive 3D designer:
- T-shirt customization
- Multiple views (front/back/map)
- Design remixing
- Save and share designs
- Submit to Spotlight gallery

### 3. Stylist Inbox
Personalized shopping recommendations:
- Style quiz with persona mapping
- Product recommendations
- Email integration
- Account dashboard integration

### 4. Admin Dashboard
Comprehensive management interface:
- Order processing
- Customer management
- Product catalog CRUD
- Marketing campaign control
- Support ticket queue
- Analytics and reporting

## Security Architecture

### Authentication & Authorization
- Session-based authentication
- Role-based access control (admin/customer)
- CSRF token protection
- Password hashing (PHP password_hash)

### Data Security
- SQL injection prevention (prepared statements)
- XSS protection (output escaping)
- Input validation and sanitization
- Rate limiting on sensitive endpoints

### Secrets Management
- Environment variables for credentials
- `.env.example` template provided
- Token-based emergency controls
- Secure session handling

## Deployment Architecture

### Development Environment
- Local XAMPP stack
- File-based development
- Direct PHP execution
- Local MySQL instance

### Production Considerations
- Apache web server with mod_php
- MySQL database server
- Windows Task Scheduler for automation
- File permissions and ownership
- Backup and restore procedures

## Integration Points

### Email System
- PHPMailer integration
- SMTP configuration
- Template-based emails
- Transaction confirmations
- Marketing communications

### Task Automation
- Windows Task Scheduler
- PowerShell wrapper scripts
- VBScript launchers (hidden execution)
- Process monitoring and alerts

### File Storage
- Local file system for uploads
- JSON files for state management
- Database for structured data
- Backup procedures documented

## Scalability Considerations

### Current Limitations
- Single-server architecture
- File-based session storage
- JSON file persistence for some features
- Synchronous request processing

### Potential Improvements
- Database-backed sessions
- Queue-based job processing
- CDN for static assets
- Horizontal scaling with load balancer
- Microservices architecture for background jobs

## Monitoring & Operations

### Health Checks
- Scheduler watchdog script
- Process monitoring
- Log file analysis
- Database connection validation

### Backup Strategy
- Database dumps (scheduled)
- File system backups
- State file versioning
- Point-in-time recovery

### Incident Response
- Emergency deactivation scripts
- Rollback procedures
- Contact escalation paths
- Security vulnerability reporting

## Development Workflow

### Version Control
- Git-based workflow
- Feature branches
- Pull request reviews
- Semantic versioning

### Testing Strategy
- PHPUnit for unit tests
- Manual QA for UI changes
- Dry-run mode for schedulers
- Staging environment validation

### Code Quality
- PHP linting (`php -l`)
- PSR-12 style guidelines
- Code reviews
- Checksum verification

## File Structure Overview

```
Sem_5_Project/
├── admin/                    # Admin panel pages
│   ├── dashboard.php
│   ├── products.php
│   ├── orders.php
│   └── ...
├── core/                     # Business logic modules
├── css/                      # Stylesheets
├── database/                 # Database files and migrations
│   ├── mystic_clothing.sql
│   └── docs/
├── docs/                     # Documentation
├── emails/                   # Email templates
├── image/                    # Static images
├── js/                       # JavaScript files
│   └── core/                # Core JS modules
├── partials/                 # Reusable PHP components
├── scripts/                  # Automation scripts
│   ├── drop_scheduler.php
│   └── *.ps1                # PowerShell scripts
├── storage/                  # File-based storage
│   └── logs/                # Application logs
├── tests/                    # PHPUnit tests
├── .env.example             # Environment template
├── composer.json            # PHP dependencies
├── index.php                # Homepage
└── README.md                # Quick start guide
```

## API Endpoints

### Public APIs
- Cart operations (add/remove/update)
- Waitlist enrollment
- Contact form submission
- Design saving
- Style quiz submission

### Admin APIs
- Product management
- Order processing
- Customer CRUD
- Marketing campaign control
- Spotlight approval

### CLI Commands
- Drop scheduler execution
- State archival
- Report generation
- Emergency deactivation
- Database backups

## Best Practices

### Code Organization
- Separate concerns (presentation, business logic, data access)
- Reusable components in `core/` and `partials/`
- Consistent naming conventions
- Comprehensive comments for complex logic

### Error Handling
- Graceful error messages
- Logging for debugging
- User-friendly error pages
- Admin notification for critical errors

### Performance
- Database query optimization
- Minimal external dependencies
- Efficient file I/O
- Caching strategies where applicable

### Maintainability
- Documentation for complex features
- Version control best practices
- Regular dependency updates
- Automated testing coverage

---

For more detailed information, see:
- [Database Schema Documentation](database/docs/README.md)
- [Operations Guide](ops.md)
- [Developer Onboarding Guide](guides/DEVELOPER_GUIDE.md)
- [API Documentation](api/API_REFERENCE.md)
