# Database Schema Documentation

## Overview

The Mystic Clothing platform uses MySQL/MariaDB as its primary database system. The schema supports e-commerce operations, user management, custom designs, support ticketing, and marketing automation.

## Database Connection

### Configuration
Database credentials are configured through environment variables or a configuration file:

```php
// Typical connection parameters
DB_HOST=127.0.0.1
DB_NAME=mystic_clothing
DB_USER=root
DB_PASS=your_secure_password
```

### Schema Import
The complete schema is available in `database/mystic_clothing.sql`. Import using:

```bash
# Using mysql command line
mysql -u root -p mystic_clothing < database/mystic_clothing.sql

# Or using phpMyAdmin
# Navigate to Import tab and select the SQL file
```

## Core Tables

### Users & Authentication

#### `users` Table
Stores customer account information.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique user identifier |
| `username` | VARCHAR(50) | UNIQUE, NOT NULL | Login username |
| `email` | VARCHAR(100) | UNIQUE, NOT NULL | Email address |
| `password` | VARCHAR(255) | NOT NULL | Hashed password |
| `first_name` | VARCHAR(50) | | First name |
| `last_name` | VARCHAR(50) | | Last name |
| `phone` | VARCHAR(20) | | Contact phone number |
| `role` | ENUM('customer', 'admin') | DEFAULT 'customer' | User role |
| `reward_points` | INT | DEFAULT 0 | Loyalty points balance |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Account creation date |
| `updated_at` | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | Last update timestamp |
| `is_active` | BOOLEAN | DEFAULT TRUE | Account status |

**Indexes:**
- PRIMARY KEY on `id`
- UNIQUE INDEX on `email`
- UNIQUE INDEX on `username`
- INDEX on `role` for admin queries

#### `user_addresses` Table
Stores shipping and billing addresses.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Address identifier |
| `user_id` | INT | FOREIGN KEY → users(id) | Owner of address |
| `address_type` | ENUM('shipping', 'billing') | | Address purpose |
| `full_name` | VARCHAR(100) | | Recipient name |
| `address_line1` | VARCHAR(255) | NOT NULL | Street address |
| `address_line2` | VARCHAR(255) | | Apt/Suite/Unit |
| `city` | VARCHAR(100) | NOT NULL | City |
| `state` | VARCHAR(50) | NOT NULL | State/Province |
| `postal_code` | VARCHAR(20) | NOT NULL | ZIP/Postal code |
| `country` | VARCHAR(50) | DEFAULT 'USA' | Country |
| `phone` | VARCHAR(20) | | Contact phone |
| `is_default` | BOOLEAN | DEFAULT FALSE | Default address flag |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Creation timestamp |

**Indexes:**
- PRIMARY KEY on `id`
- INDEX on `user_id`
- INDEX on `is_default`

### Product Catalog

#### `products` Table
Main product catalog.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Product identifier |
| `name` | VARCHAR(200) | NOT NULL | Product name |
| `slug` | VARCHAR(255) | UNIQUE | URL-friendly name |
| `description` | TEXT | | Detailed description |
| `short_description` | VARCHAR(500) | | Brief description |
| `price` | DECIMAL(10,2) | NOT NULL | Base price |
| `sale_price` | DECIMAL(10,2) | | Discounted price |
| `category` | VARCHAR(50) | | Product category |
| `subcategory` | VARCHAR(50) | | Product subcategory |
| `sku` | VARCHAR(50) | UNIQUE | Stock keeping unit |
| `stock_quantity` | INT | DEFAULT 0 | Available inventory |
| `image_url` | VARCHAR(255) | | Primary image path |
| `is_featured` | BOOLEAN | DEFAULT FALSE | Featured flag |
| `is_active` | BOOLEAN | DEFAULT TRUE | Active status |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Creation date |
| `updated_at` | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | Last update |

**Indexes:**
- PRIMARY KEY on `id`
- UNIQUE INDEX on `slug`
- UNIQUE INDEX on `sku`
- INDEX on `category`
- INDEX on `is_featured`
- INDEX on `is_active`

#### `product_images` Table
Additional product images.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Image identifier |
| `product_id` | INT | FOREIGN KEY → products(id) | Associated product |
| `image_url` | VARCHAR(255) | NOT NULL | Image file path |
| `alt_text` | VARCHAR(255) | | Image alt text |
| `display_order` | INT | DEFAULT 0 | Sort order |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Upload timestamp |

**Indexes:**
- PRIMARY KEY on `id`
- INDEX on `product_id`
- INDEX on `display_order`

#### `product_variants` Table
Product size/color variations.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Variant identifier |
| `product_id` | INT | FOREIGN KEY → products(id) | Parent product |
| `size` | VARCHAR(20) | | Size option (XS, S, M, L, XL, etc.) |
| `color` | VARCHAR(50) | | Color option |
| `sku` | VARCHAR(50) | UNIQUE | Variant SKU |
| `price_modifier` | DECIMAL(10,2) | DEFAULT 0 | Price adjustment |
| `stock_quantity` | INT | DEFAULT 0 | Variant inventory |
| `is_active` | BOOLEAN | DEFAULT TRUE | Active status |

**Indexes:**
- PRIMARY KEY on `id`
- INDEX on `product_id`
- UNIQUE INDEX on `sku`

### Orders & Transactions

#### `orders` Table
Customer orders.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Order identifier |
| `user_id` | INT | FOREIGN KEY → users(id) | Customer |
| `order_number` | VARCHAR(50) | UNIQUE | Display order number |
| `status` | ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') | DEFAULT 'pending' | Order status |
| `subtotal` | DECIMAL(10,2) | NOT NULL | Items subtotal |
| `tax` | DECIMAL(10,2) | DEFAULT 0 | Tax amount |
| `shipping` | DECIMAL(10,2) | DEFAULT 0 | Shipping cost |
| `discount` | DECIMAL(10,2) | DEFAULT 0 | Discount amount |
| `total` | DECIMAL(10,2) | NOT NULL | Final total |
| `payment_method` | VARCHAR(50) | | Payment type |
| `payment_status` | ENUM('pending', 'paid', 'failed', 'refunded') | DEFAULT 'pending' | Payment status |
| `shipping_address_id` | INT | FOREIGN KEY → user_addresses(id) | Shipping destination |
| `tracking_number` | VARCHAR(100) | | Shipment tracking |
| `notes` | TEXT | | Order notes |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Order date |
| `updated_at` | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | Last update |

**Indexes:**
- PRIMARY KEY on `id`
- UNIQUE INDEX on `order_number`
- INDEX on `user_id`
- INDEX on `status`
- INDEX on `payment_status`
- INDEX on `created_at`

#### `order_items` Table
Line items in orders.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Item identifier |
| `order_id` | INT | FOREIGN KEY → orders(id) | Parent order |
| `product_id` | INT | FOREIGN KEY → products(id) | Ordered product |
| `variant_id` | INT | FOREIGN KEY → product_variants(id) | Product variant |
| `product_name` | VARCHAR(200) | | Snapshot of name |
| `quantity` | INT | NOT NULL | Items ordered |
| `price` | DECIMAL(10,2) | NOT NULL | Unit price at time of order |
| `subtotal` | DECIMAL(10,2) | NOT NULL | Line item total |
| `design_id` | INT | FOREIGN KEY → designs(id) | Custom design reference |

**Indexes:**
- PRIMARY KEY on `id`
- INDEX on `order_id`
- INDEX on `product_id`

### Shopping Cart

#### `cart` Table
Active shopping cart items.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Cart item identifier |
| `user_id` | INT | FOREIGN KEY → users(id) | Cart owner (NULL for guest) |
| `session_id` | VARCHAR(255) | | Session identifier for guests |
| `product_id` | INT | FOREIGN KEY → products(id) | Product in cart |
| `variant_id` | INT | FOREIGN KEY → product_variants(id) | Selected variant |
| `quantity` | INT | NOT NULL, DEFAULT 1 | Item quantity |
| `design_id` | INT | FOREIGN KEY → designs(id) | Custom design |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Added to cart |
| `updated_at` | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | Last modified |

**Indexes:**
- PRIMARY KEY on `id`
- INDEX on `user_id`
- INDEX on `session_id`
- INDEX on `product_id`

### Custom Designs

#### `designs` Table
User-created custom designs.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Design identifier |
| `user_id` | INT | FOREIGN KEY → users(id) | Creator |
| `title` | VARCHAR(200) | | Design title |
| `design_data` | TEXT | | JSON design configuration |
| `preview_front` | VARCHAR(255) | | Front view image |
| `preview_back` | VARCHAR(255) | | Back view image |
| `preview_map` | VARCHAR(255) | | Design map image |
| `is_public` | BOOLEAN | DEFAULT FALSE | Public gallery flag |
| `remix_source_id` | INT | FOREIGN KEY → designs(id) | Original design if remix |
| `spotlight_status` | ENUM('pending', 'approved', 'rejected', 'removed') | NULL | Spotlight submission status |
| `likes_count` | INT | DEFAULT 0 | Number of likes |
| `saves_count` | INT | DEFAULT 0 | Number of saves |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Creation date |
| `updated_at` | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | Last edit |

**Indexes:**
- PRIMARY KEY on `id`
- INDEX on `user_id`
- INDEX on `is_public`
- INDEX on `spotlight_status`
- INDEX on `remix_source_id`

#### `design_likes` Table
User likes on designs.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Like identifier |
| `user_id` | INT | FOREIGN KEY → users(id) | User who liked |
| `design_id` | INT | FOREIGN KEY → designs(id) | Liked design |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Like timestamp |

**Indexes:**
- PRIMARY KEY on `id`
- UNIQUE INDEX on (`user_id`, `design_id`)
- INDEX on `design_id`

### Support & Feedback

#### `support_tickets` Table
Customer support tickets.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Ticket identifier |
| `ticket_number` | VARCHAR(50) | UNIQUE | Display ticket number |
| `user_id` | INT | FOREIGN KEY → users(id) | Ticket creator |
| `subject` | VARCHAR(255) | NOT NULL | Ticket subject |
| `message` | TEXT | NOT NULL | Initial message |
| `category` | VARCHAR(50) | | Issue category |
| `priority` | ENUM('low', 'medium', 'high', 'urgent') | DEFAULT 'medium' | Priority level |
| `status` | ENUM('open', 'in_progress', 'resolved', 'closed') | DEFAULT 'open' | Ticket status |
| `assigned_to` | INT | FOREIGN KEY → users(id) | Admin assignee |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Creation date |
| `updated_at` | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | Last update |
| `resolved_at` | TIMESTAMP | NULL | Resolution date |

**Indexes:**
- PRIMARY KEY on `id`
- UNIQUE INDEX on `ticket_number`
- INDEX on `user_id`
- INDEX on `status`
- INDEX on `assigned_to`

#### `support_messages` Table
Messages within support tickets.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Message identifier |
| `ticket_id` | INT | FOREIGN KEY → support_tickets(id) | Parent ticket |
| `user_id` | INT | FOREIGN KEY → users(id) | Message author |
| `message` | TEXT | NOT NULL | Message content |
| `is_internal` | BOOLEAN | DEFAULT FALSE | Internal note flag |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Message timestamp |

**Indexes:**
- PRIMARY KEY on `id`
- INDEX on `ticket_id`
- INDEX on `created_at`

### Marketing & Promotions

#### `waitlists` Table
Product drop waitlist enrollments.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Entry identifier |
| `email` | VARCHAR(100) | NOT NULL | Enrollee email |
| `drop_slug` | VARCHAR(100) | NOT NULL | Drop identifier |
| `user_id` | INT | FOREIGN KEY → users(id) | Associated user (if logged in) |
| `ip_hash` | VARCHAR(64) | | Hashed IP for rate limiting |
| `source` | VARCHAR(50) | | Enrollment source |
| `notified` | BOOLEAN | DEFAULT FALSE | Notification sent flag |
| `converted` | BOOLEAN | DEFAULT FALSE | Purchased flag |
| `enrolled_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Enrollment date |

**Indexes:**
- PRIMARY KEY on `id`
- INDEX on `drop_slug`
- INDEX on `email`
- INDEX on `user_id`
- INDEX on `notified`

#### `style_quiz_results` Table
Style quiz persona results.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Result identifier |
| `user_id` | INT | FOREIGN KEY → users(id) | Quiz taker |
| `session_id` | VARCHAR(255) | | Session identifier for guests |
| `persona_style` | VARCHAR(50) | | Style dimension |
| `persona_palette` | VARCHAR(50) | | Color dimension |
| `persona_goal` | VARCHAR(50) | | Shopping goal dimension |
| `quiz_data` | TEXT | | Full quiz response JSON |
| `recommendations` | TEXT | | Recommended products JSON |
| `email_sent` | BOOLEAN | DEFAULT FALSE | Email notification flag |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Quiz completion date |

**Indexes:**
- PRIMARY KEY on `id`
- INDEX on `user_id`
- INDEX on `session_id`

## Relationships Diagram

```
users (1) ──< (M) orders
users (1) ──< (M) cart
users (1) ──< (M) designs
users (1) ──< (M) support_tickets
users (1) ──< (M) user_addresses

products (1) ──< (M) product_images
products (1) ──< (M) product_variants
products (1) ──< (M) order_items
products (1) ──< (M) cart

orders (1) ──< (M) order_items
orders (1) ──> (1) user_addresses

designs (1) ──< (M) design_likes
designs (1) ──< (M) order_items (custom)
designs (1) ──< (M) designs (remix)

support_tickets (1) ──< (M) support_messages
```

## Stored Procedures & Functions

### Common Queries

#### Get Active Cart for User
```sql
SELECT c.*, p.name, p.price, pv.size, pv.color
FROM cart c
JOIN products p ON c.product_id = p.id
LEFT JOIN product_variants pv ON c.variant_id = pv.id
WHERE c.user_id = ?
ORDER BY c.created_at DESC;
```

#### Get User Orders with Items
```sql
SELECT o.*, oi.*, p.name as product_name
FROM orders o
JOIN order_items oi ON o.id = oi.order_id
JOIN products p ON oi.product_id = p.id
WHERE o.user_id = ?
ORDER BY o.created_at DESC;
```

#### Get Featured Products
```sql
SELECT * FROM products
WHERE is_featured = TRUE AND is_active = TRUE
ORDER BY created_at DESC
LIMIT 10;
```

## Data Migrations

Migration files are stored in `database/migrations/` directory. Each migration follows the naming convention:

```
YYYY_MM_DD_HHMMSS_description.sql
```

### Running Migrations
```bash
# Sequential execution recommended
for file in database/migrations/*.sql; do
    mysql -u root -p mystic_clothing < "$file"
done
```

## Backup & Restore

### Creating Backups
```bash
# Full database backup
mysqldump -u root -p mystic_clothing > backup_$(date +%Y%m%d_%H%M%S).sql

# Schema only
mysqldump -u root -p --no-data mystic_clothing > schema_backup.sql

# Data only
mysqldump -u root -p --no-create-info mystic_clothing > data_backup.sql
```

### Restoring from Backup
```bash
# Restore full backup
mysql -u root -p mystic_clothing < backup_20241116_120000.sql
```

## Performance Optimization

### Recommended Indexes
All critical indexes are defined in the schema. Monitor slow queries using:

```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- View slow queries
SELECT * FROM mysql.slow_log;
```

### Query Optimization Tips
1. Use prepared statements to prevent SQL injection and improve performance
2. Add indexes on frequently queried columns
3. Avoid SELECT * in production code
4. Use LIMIT for paginated results
5. Consider denormalization for read-heavy tables (e.g., likes_count)

## Security Considerations

### Sensitive Data
- Passwords are hashed using PHP's `password_hash()`
- Credit card data is NOT stored (use payment gateway tokens)
- Personal information should be encrypted at rest in production
- IP addresses are hashed before storage

### Access Control
- Use separate database users for web application and admin tasks
- Grant minimum necessary privileges
- Regular password rotation
- Enable SSL/TLS for database connections in production

## Maintenance Tasks

### Regular Maintenance
```sql
-- Optimize tables
OPTIMIZE TABLE users, orders, products;

-- Analyze tables for query optimization
ANALYZE TABLE users, orders, products;

-- Check table integrity
CHECK TABLE users, orders, products;
```

### Data Cleanup
```sql
-- Remove old cart items (older than 30 days)
DELETE FROM cart WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Archive old orders (older than 1 year)
-- Move to archive table before deletion
INSERT INTO orders_archive SELECT * FROM orders 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

## Additional Resources

- Full schema: `database/mystic_clothing.sql`
- Restore instructions: `database/docs/restore_instructions.md`
- Operations playbook: `database/docs/OPS_PLAYBOOK.md`

---

For questions or schema change requests, please refer to the [Contributing Guide](../CONTRIBUTING.md).
