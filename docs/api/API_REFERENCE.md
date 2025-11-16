# API Reference

## Overview

The Mystic Clothing platform provides both browser-based and CLI APIs for various operations. This document covers all available endpoints and their usage.

## Table of Contents

- [Authentication](#authentication)
- [Public APIs](#public-apis)
  - [Cart Management](#cart-management)
  - [Waitlist Enrollment](#waitlist-enrollment)
  - [Contact & Support](#contact--support)
  - [Design Management](#design-management)
  - [Style Quiz](#style-quiz)
- [Admin APIs](#admin-apis)
- [CLI Commands](#cli-commands)
- [Error Handling](#error-handling)

---

## Authentication

### Session-Based Authentication

The platform uses PHP sessions for authentication. Sessions are established on login and maintained via cookies.

#### Login
**Endpoint:** `POST /login_handler.php`

**Request Parameters:**
```
username: string (required)
password: string (required)
remember_me: boolean (optional)
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "redirect": "/account.php"
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

#### Logout
**Endpoint:** `GET /logout.php`

Destroys session and redirects to homepage.

#### Password Reset
**Endpoint:** `POST /send_reset_link.php`

**Request Parameters:**
```
email: string (required)
```

**Response:**
```json
{
  "success": true,
  "message": "Reset link sent to your email"
}
```

---

## Public APIs

### Cart Management

#### Add to Cart
**Endpoint:** `POST /cart_handler.php`

**Parameters:**
```
action: "add"
product_id: integer (required)
variant_id: integer (optional)
quantity: integer (default: 1)
design_id: integer (optional, for custom designs)
```

**Response:**
```json
{
  "success": true,
  "message": "Item added to cart",
  "cart_count": 5
}
```

#### Update Cart Item
**Endpoint:** `POST /cart_handler.php`

**Parameters:**
```
action: "update"
cart_item_id: integer (required)
quantity: integer (required)
```

**Response:**
```json
{
  "success": true,
  "message": "Cart updated",
  "new_subtotal": 59.99
}
```

#### Remove from Cart
**Endpoint:** `POST /cart_handler.php`

**Parameters:**
```
action: "remove"
cart_item_id: integer (required)
```

**Response:**
```json
{
  "success": true,
  "message": "Item removed from cart"
}
```

#### Get Cart Items
**Endpoint:** `GET /cart.php` (via AJAX)

**Response:**
```json
{
  "items": [
    {
      "id": 123,
      "product_id": 45,
      "product_name": "Classic T-Shirt",
      "price": 29.99,
      "quantity": 2,
      "variant": {
        "size": "L",
        "color": "Black"
      },
      "subtotal": 59.98
    }
  ],
  "subtotal": 59.98,
  "tax": 5.40,
  "shipping": 10.00,
  "total": 75.38
}
```

### Waitlist Enrollment

#### Enroll in Drop Waitlist
**Endpoint:** `POST /drop_waitlist_enroll.php`

**Parameters:**
```
email: string (required, valid email)
drop_slug: string (required)
csrf_token: string (required)
source: string (optional, tracking parameter)
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "You're on the waitlist! We'll notify you when the drop goes live."
}
```

**Response (Already Enrolled):**
```json
{
  "status": "exists",
  "message": "You're already on the waitlist for this drop."
}
```

**Response (Rate Limited):**
```json
{
  "status": "rate_limited",
  "message": "Too many requests. Please try again later.",
  "retry_after": 300
}
```
**HTTP Status:** 429 Too Many Requests

**Rate Limits:**
- 3 enrollments per IP per drop per hour
- 10 enrollments per email per day across all drops

### Contact & Support

#### Submit Contact Form
**Endpoint:** `POST /contact_handler.php`

**Parameters:**
```
name: string (required, max 100 chars)
email: string (required, valid email)
subject: string (required, max 200 chars)
message: text (required, max 2000 chars)
csrf_token: string (required)
```

**Response:**
```json
{
  "success": true,
  "message": "Thank you for contacting us. We'll respond within 24 hours."
}
```

#### Create Support Ticket
**Endpoint:** `POST /support_issue_handler.php`

**Parameters:**
```
subject: string (required)
message: text (required)
category: string (optional: "order", "product", "technical", "other")
priority: string (optional: "low", "medium", "high")
order_number: string (optional, for order-related issues)
```

**Response:**
```json
{
  "success": true,
  "ticket_number": "TKT-20241116-001",
  "message": "Support ticket created successfully"
}
```

#### Submit Feedback
**Endpoint:** `POST /submit_feedback.php`

**Parameters:**
```
type: string ("compliment", "suggestion", "issue")
message: text (required)
rating: integer (1-5, optional)
```

**Response:**
```json
{
  "success": true,
  "message": "Thank you for your feedback!"
}
```

### Design Management

#### Save Custom Design
**Endpoint:** `POST /save_design.php`

**Parameters:**
```
title: string (required, max 200 chars)
design_data: JSON string (required, design configuration)
preview_front: base64 image (optional)
preview_back: base64 image (optional)
preview_map: base64 image (optional)
is_public: boolean (default: false)
remix_source_id: integer (optional)
```

**Response:**
```json
{
  "success": true,
  "design_id": 789,
  "message": "Design saved successfully"
}
```

#### Get Design
**Endpoint:** `GET /get_design.php?id={design_id}`

**Response:**
```json
{
  "id": 789,
  "title": "My Awesome Design",
  "design_data": {
    "front": {...},
    "back": {...}
  },
  "preview_front": "/uploads/designs/789_front.png",
  "preview_back": "/uploads/designs/789_back.png",
  "is_public": false,
  "created_at": "2024-11-16T10:30:00Z"
}
```

#### Submit Design to Spotlight
**Endpoint:** `POST /submit_design_spotlight.php`

**Parameters:**
```
design_id: integer (required)
description: text (optional, max 500 chars)
tags: string (optional, comma-separated)
```

**Response:**
```json
{
  "success": true,
  "message": "Design submitted for review",
  "spotlight_status": "pending"
}
```

### Style Quiz

#### Submit Style Quiz
**Endpoint:** `POST /style_quiz_handler.php`

**Parameters:**
```
style_preference: string (required: "classic", "trendy", "edgy", "boho")
color_palette: string (required: "neutrals", "pastels", "bold", "dark")
shopping_goal: string (required: "basics", "statement", "work", "casual")
email: string (optional, for results email)
```

**Response:**
```json
{
  "success": true,
  "persona": {
    "style": "classic",
    "palette": "neutrals",
    "goal": "basics"
  },
  "recommendations": [
    {
      "product_id": 45,
      "name": "Classic White Tee",
      "price": 29.99,
      "image": "/images/products/45.jpg",
      "match_score": 95
    }
  ],
  "email_sent": true
}
```

---

## Admin APIs

**Note:** All admin APIs require authentication and admin role.

### Product Management

#### Create Product
**Endpoint:** `POST /admin/products.php`

**Parameters:**
```
action: "create"
name: string (required)
description: text (required)
price: decimal (required)
category: string (required)
sku: string (required, unique)
stock_quantity: integer (default: 0)
images[]: file uploads (optional)
```

**Response:**
```json
{
  "success": true,
  "product_id": 123,
  "message": "Product created successfully"
}
```

#### Update Product
**Endpoint:** `POST /admin/products.php`

**Parameters:**
```
action: "update"
product_id: integer (required)
[any product fields to update]
```

#### Delete Product
**Endpoint:** `POST /admin/products.php`

**Parameters:**
```
action: "delete"
product_id: integer (required)
```

### Order Management

#### Update Order Status
**Endpoint:** `POST /admin/orders.php`

**Parameters:**
```
action: "update_status"
order_id: integer (required)
status: string (required: "pending", "processing", "shipped", "delivered", "cancelled")
tracking_number: string (optional)
notes: text (optional)
```

**Response:**
```json
{
  "success": true,
  "message": "Order status updated",
  "notification_sent": true
}
```

### Marketing & Promotions

#### Create Drop Promotion
**Endpoint:** `POST /admin/marketing.php`

**Parameters:**
```
action: "create_drop"
drop_label: string (required)
product_ids: array of integers (required)
start_time: datetime (required)
end_time: datetime (required)
visibility: string ("all", "subscribers", "vip")
countdown_enabled: boolean (default: true)
banner_message: text (optional)
```

**Response:**
```json
{
  "success": true,
  "drop_slug": "summer-collection-2024",
  "message": "Drop promotion created",
  "scheduled_start": "2024-06-01T00:00:00Z"
}
```

### Spotlight Management

#### Approve Design
**Endpoint:** `POST /admin/spotlight.php`

**Parameters:**
```
action: "approve"
design_id: integer (required)
featured: boolean (default: false)
```

**Response:**
```json
{
  "success": true,
  "message": "Design approved and published"
}
```

#### Reject Design
**Endpoint:** `POST /admin/spotlight.php`

**Parameters:**
```
action: "reject"
design_id: integer (required)
reason: text (optional)
```

---

## CLI Commands

### Drop Scheduler

#### Run Drop Scheduler
```bash
php scripts/drop_scheduler.php [options]

Options:
  --dry-run          Simulate without making changes
  --verbose          Enable detailed logging
  --force            Bypass environment checks
```

**Exit Codes:**
- 0: Success
- 1: General error
- 2: Configuration error
- 3: Database error

#### Emergency Deactivation
```bash
php scripts/force_deactivate.php --token=<SECRET_TOKEN> [options]

Options:
  --drop-slug=<slug>     Specific drop to deactivate
  --all                  Deactivate all active drops
  --backup-only          Create backup without deactivation
```

**Response:**
```
[2024-11-16 10:30:45] Backup created: storage/backups/drop_state_20241116_103045.json
[2024-11-16 10:30:46] Drop 'summer-collection' deactivated successfully
[2024-11-16 10:30:46] Audit entry added
```

### Drop Promotion Management

#### Take Snapshot
```bash
php scripts/drop_promotion_snapshot.php [options]

Options:
  --expect-status=<status>    Validate expected status (idle|active|scheduled)
  --expect-slug=<slug>        Validate expected active drop slug
  --json                      Output in JSON format
```

**JSON Response:**
```json
{
  "timestamp": "2024-11-16T10:30:45Z",
  "status": "active",
  "active_drops": [
    {
      "slug": "summer-collection",
      "label": "Summer Collection 2024",
      "start_time": "2024-06-01T00:00:00Z",
      "end_time": "2024-06-30T23:59:59Z",
      "visibility": "all"
    }
  ]
}
```

#### Sync Promotion
```bash
php scripts/drop_promotion_sync.php [options]

Options:
  --activate=<slug>      Activate a scheduled drop
  --deactivate=<slug>    Deactivate an active drop
  --status               Show current promotion status
```

### Reporting

#### Waitlist Conversion Report
```bash
php scripts/waitlist_conversion_report.php [options]

Options:
  --drop-slug=<slug>     Report for specific drop
  --format=<format>      Output format (text|json|csv)
  --output=<file>        Write to file instead of stdout
```

**CSV Output:**
```csv
drop_slug,total_enrolled,notified,converted,conversion_rate
summer-collection,1523,1523,287,18.8%
```

### Database Utilities

#### Backup Database
```powershell
powershell -ExecutionPolicy Bypass -File scripts/db_backup.ps1 [options]

Options:
  -OutputPath <path>     Backup file path
  -DatabaseName <name>   Database name (default: mystic_clothing)
  -Compress              Create compressed backup
```

#### Generate PHP Checksums
```bash
php scripts/generate_php_checksums.php

# Or using PowerShell
powershell -ExecutionPolicy Bypass -File scripts/generate_php_checksums.ps1
```

**Output:** Updates `docs/php_checksums.txt` with SHA256 hashes of all PHP files.

---

## Error Handling

### Standard Error Response Format

All APIs return errors in a consistent format:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {
      "field": "specific_field_with_error",
      "value": "invalid_value"
    }
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `INVALID_REQUEST` | 400 | Malformed request or missing required parameters |
| `UNAUTHORIZED` | 401 | Authentication required |
| `FORBIDDEN` | 403 | Insufficient permissions |
| `NOT_FOUND` | 404 | Resource not found |
| `CONFLICT` | 409 | Resource already exists or state conflict |
| `RATE_LIMITED` | 429 | Too many requests |
| `VALIDATION_ERROR` | 422 | Invalid input data |
| `SERVER_ERROR` | 500 | Internal server error |
| `SERVICE_UNAVAILABLE` | 503 | Service temporarily unavailable |

### CSRF Protection

All state-changing requests (POST, PUT, DELETE) require a valid CSRF token:

```html
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
```

**Validation:**
```php
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}
```

### Rate Limiting

Rate limits are applied to prevent abuse:

| Endpoint | Limit | Window |
|----------|-------|--------|
| Waitlist Enrollment | 3 per IP/drop | 1 hour |
| Contact Form | 5 per IP | 1 hour |
| Login Attempts | 5 per account | 15 minutes |
| API General | 100 per IP | 1 minute |

**Rate Limit Headers:**
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1699876543
```

### Validation Rules

#### Email
- Must be valid email format
- Max length: 100 characters
- Must be unique for user registration

#### Password
- Min length: 8 characters
- Must contain: uppercase, lowercase, number
- Max length: 255 characters

#### Phone
- Format: (XXX) XXX-XXXX or XXX-XXX-XXXX
- Optional country code: +1 XXX-XXX-XXXX

#### Product Price
- Min: 0.01
- Max: 9999.99
- Decimal places: 2

---

## Webhooks (Future)

Webhook support is planned for the following events:

- `order.created`
- `order.status_changed`
- `order.shipped`
- `product.low_stock`
- `drop.activated`
- `drop.deactivated`
- `design.spotlight_approved`

**Webhook Payload Format:**
```json
{
  "event": "order.created",
  "timestamp": "2024-11-16T10:30:45Z",
  "data": {
    "order_id": 123,
    "order_number": "ORD-20241116-001",
    "total": 75.38
  }
}
```

---

## API Versioning

Currently, the API is unversioned. Future versions will use URL-based versioning:

```
/api/v1/endpoint
/api/v2/endpoint
```

---

## SDK & Client Libraries

Official client libraries are planned for:

- JavaScript/Node.js
- Python
- PHP (for external integrations)

## Additional Resources

- [Architecture Overview](ARCHITECTURE.md)
- [Database Schema](DATABASE_SCHEMA.md)
- [Developer Guide](guides/DEVELOPER_GUIDE.md)
- [Security Policy](../SECURITY.md)

---

**Last Updated:** November 16, 2024  
**API Version:** Unversioned (Pre-1.0)
