# Admin Guide - Mystic Clothing

This guide covers administrative functions and management of the Mystic Clothing platform.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Dashboard Overview](#dashboard-overview)
3. [Product Management](#product-management)
4. [Order Management](#order-management)
5. [Customer Management](#customer-management)
6. [Marketing & Promotions](#marketing--promotions)
7. [Spotlight Gallery](#spotlight-gallery)
8. [Support Queue](#support-queue)
9. [Reports & Analytics](#reports--analytics)
10. [System Administration](#system-administration)

---

## Getting Started

### Accessing Admin Panel

1. Navigate to `/admin/` or click **Admin** in menu
2. Log in with admin credentials
3. You'll see the admin dashboard

**Security Note:** Admin accounts should use strong passwords and be changed regularly.

### Admin Navigation

The admin panel includes:
- **Dashboard** - Overview and metrics
- **Products** - Catalog management
- **Orders** - Order processing
- **Customers** - User management
- **Marketing** - Promotions and campaigns
- **Spotlight** - Design gallery curation
- **Support** - Customer support queue
- **Reports** - Analytics and insights

---

## Dashboard Overview

The dashboard provides at-a-glance metrics:

### Key Metrics

**Sales:**
- Today's revenue
- Weekly revenue
- Monthly revenue
- Top-selling products

**Orders:**
- Pending orders count
- Processing orders
- Shipped orders
- Completed orders

**Customers:**
- New registrations
- Active users
- Total customers

**Inventory:**
- Low stock alerts
- Out of stock items
- Total products

### Quick Actions

- Create new product
- Process pending order
- View support tickets
- Launch promotion

### Recent Activity

- Latest orders
- New customer registrations
- Recent support tickets
- New design submissions

---

## Product Management

### Viewing Products

**Products List:**
1. Go to **Products** in admin menu
2. View all products in table format:
   - Product image
   - Name and SKU
   - Category
   - Price
   - Stock
   - Status (Active/Inactive)
3. Use search and filters

**Filters:**
- Category
- Status (Active/Inactive)
- Stock level (In Stock/Low Stock/Out of Stock)
- Featured status

### Adding Products

1. Click **Add New Product**
2. Fill in product details:

**Basic Information:**
- Product name (required)
- SKU (unique identifier)
- Category/Subcategory
- Short description (for listings)
- Full description (for product page)

**Pricing:**
- Base price (required)
- Sale price (optional)
- Compare at price (optional)

**Inventory:**
- Stock quantity
- Track inventory (yes/no)
- Allow backorders (yes/no)

**Media:**
- Upload product images
- Set main image
- Add additional views
- Add videos (if supported)

**Variants:**
- Add size options (XS, S, M, L, XL, XXL)
- Add color options
- Set SKU and stock per variant
- Price modifiers per variant

**SEO:**
- Page title
- Meta description
- URL slug

3. Click **Save Product**

### Editing Products

1. Find product in list
2. Click **Edit** icon
3. Modify any fields
4. Click **Update Product**

**Bulk Actions:**
- Select multiple products
- Choose action:
  - Activate/Deactivate
  - Set as Featured
  - Update category
  - Delete
- Apply

### Product Categories

**Manage Categories:**
1. Go to **Products** > **Categories**
2. View existing categories
3. Add new category
4. Edit/delete categories

**Category Fields:**
- Name
- Slug (URL-friendly)
- Description
- Parent category (for subcategories)
- Display order

### Inventory Management

**Stock Updates:**
1. Go to product edit page
2. Update stock quantity
3. Save changes

**Low Stock Alerts:**
- Set threshold per product
- Receive notifications
- View alert dashboard

**Bulk Stock Update:**
1. Export products to CSV
2. Update quantities
3. Import CSV
4. Review and confirm

---

## Order Management

### Viewing Orders

**Orders List:**
1. Go to **Orders** in admin menu
2. View all orders:
   - Order number
   - Customer name
   - Date
   - Status
   - Total amount
3. Click order to view details

**Filter Orders:**
- By status
- By date range
- By customer
- By payment status

**Sort Options:**
- Newest first
- Oldest first
- Highest amount
- Lowest amount

### Order Details

Each order shows:
- Order number and date
- Customer information
- Shipping address
- Items ordered (with quantities)
- Pricing breakdown
- Payment info
- Order status history
- Customer notes

### Processing Orders

#### Pending Orders

1. Review order details
2. Verify payment received
3. Check inventory availability
4. Update status to **Processing**
5. Add internal notes if needed

#### Preparing for Shipment

1. Print packing slip
2. Pull items from inventory
3. Package order
4. Print shipping label
5. Update status to **Shipped**
6. Enter tracking number
7. System sends notification email

#### Tracking Updates

- Enter tracking number
- Select carrier
- Update will appear on order page
- Customer receives notification

### Order Status Management

**Available Statuses:**
- **Pending:** Awaiting processing
- **Processing:** Being prepared
- **Shipped:** On the way
- **Delivered:** Received by customer
- **Cancelled:** Order cancelled
- **Refunded:** Money returned

**Change Status:**
1. Open order details
2. Select new status from dropdown
3. Add notes (optional)
4. Click **Update Status**
5. Customer is notified automatically

### Refunds & Cancellations

**Cancel Order:**
1. Open order details
2. Click **Cancel Order**
3. Select reason
4. Choose refund method:
   - Full refund
   - Partial refund
   - No refund (cancel only)
5. Confirm cancellation

**Process Refund:**
1. Go to order details
2. Click **Issue Refund**
3. Enter refund amount
4. Select items to refund
5. Add reason
6. Process through payment gateway
7. Update order status
8. Customer receives notification

### Bulk Order Actions

Select multiple orders:
- Export to CSV
- Print packing slips
- Update status
- Send notifications

---

## Customer Management

### Viewing Customers

**Customer List:**
1. Go to **Customers** in admin menu
2. View all customers:
   - Name
   - Email
   - Registration date
   - Total orders
   - Total spent
   - Status

**Search:**
- By name
- By email
- By order number

**Filter:**
- By status (Active/Inactive)
- By registration date
- By order count
- By total spent

### Customer Details

Each customer profile shows:
- Personal information
- Contact details
- Order history
- Saved designs
- Support tickets
- Reward points
- Addresses
- Activity log

### Managing Customers

**Edit Customer:**
1. Click on customer
2. Modify details:
   - Name, email, phone
   - Address information
   - Reward points balance
   - Account status
3. Save changes

**Reset Password:**
1. Go to customer details
2. Click **Send Password Reset**
3. Customer receives reset email

**Account Actions:**
- Activate/Deactivate account
- Merge duplicate accounts
- Delete account (with confirmation)
- View login history

### Customer Communications

**Send Email:**
1. Go to customer details
2. Click **Send Email**
3. Choose template or write custom
4. Send

**Bulk Email:**
1. Select customers
2. Choose **Send Email**
3. Select template
4. Schedule or send now

---

## Marketing & Promotions

### Drop Promotions

**Create Drop:**
1. Go to **Marketing** > **Drops**
2. Click **Create Drop**
3. Fill in details:
   - Drop name/label
   - Products to include
   - Start date/time
   - End date/time
   - Visibility (All/Subscribers/VIP)
   - Banner message
   - Countdown enabled
4. Save as draft or schedule

**Manage Active Drops:**
- View current drops
- Edit details
- End early if needed
- View waitlist enrollments
- Track conversion rates

**Emergency Deactivation:**

If needed, use CLI tool:
```bash
php scripts/force_deactivate.php --token=YOUR_TOKEN --drop-slug=drop-name
```

### Waitlist Management

**View Waitlists:**
1. Go to **Marketing** > **Waitlists**
2. Select drop
3. View enrolled emails:
   - Email address
   - Enrollment date
   - Notified status
   - Converted status

**Actions:**
- Export waitlist to CSV
- Send notification emails
- Mark as notified
- Track conversions

### Discount Codes

(If implemented)

**Create Discount:**
1. Go to **Marketing** > **Discounts**
2. Click **Add Discount**
3. Set parameters:
   - Code name
   - Discount type (%, fixed amount)
   - Value
   - Valid dates
   - Usage limit
   - Minimum order
4. Save

### Email Campaigns

**Create Campaign:**
1. Go to **Marketing** > **Emails**
2. Click **New Campaign**
3. Design email:
   - Choose template
   - Add content
   - Insert products
4. Select recipients:
   - All customers
   - Specific segment
   - Custom list
5. Schedule or send

---

## Spotlight Gallery

### Managing Submissions

**View Submissions:**
1. Go to **Spotlight**
2. View pending designs:
   - Design preview
   - Creator name
   - Submission date
   - Design details

**Review Process:**
1. Click on submission
2. View full design
3. Check for:
   - Quality
   - Appropriateness
   - Copyright compliance
4. Decide action

### Approving Designs

1. Select design
2. Click **Approve**
3. Optional: Mark as **Featured**
4. Add tags (optional)
5. Publish to gallery
6. Creator is notified

### Rejecting Designs

1. Select design
2. Click **Reject**
3. Select reason:
   - Quality issues
   - Copyright concern
   - Inappropriate content
   - Other (specify)
4. Add message to creator
5. Submit

### Featured Designs

**Set as Featured:**
1. Go to approved designs
2. Select design
3. Click **Feature**
4. Set display order
5. Save

**Manage Featured:**
- Reorder designs
- Remove from featured
- Set duration

### Spotlight Settings

Configure:
- Submission guidelines
- Review criteria
- Auto-approval rules (if any)
- Notification templates

---

## Support Queue

### Viewing Tickets

**Ticket Queue:**
1. Go to **Support**
2. View all tickets:
   - Ticket number
   - Customer name
   - Subject
   - Category
   - Priority
   - Status
   - Last updated

**Filter Tickets:**
- By status (Open/In Progress/Resolved)
- By priority
- By category
- By assigned agent
- By date

### Managing Tickets

**Open Ticket:**
1. Click on ticket
2. View details:
   - Customer info
   - Issue description
   - Order details (if applicable)
   - Message history
   - Attachments

**Respond to Ticket:**
1. Open ticket
2. Type response
3. Add attachments if needed
4. Choose visibility:
   - Public (customer sees)
   - Internal note
5. Send

**Change Status:**
- In Progress
- Waiting on Customer
- Resolved
- Closed

**Assign Ticket:**
1. Open ticket
2. Select agent from dropdown
3. Assign
4. Agent is notified

### Ticket Categories

- Order Issues
- Product Questions
- Technical Support
- Returns/Exchanges
- Account Problems
- Other

### Priority Levels

- **Low:** General questions
- **Medium:** Non-urgent issues
- **High:** Order problems
- **Urgent:** Critical issues

### Ticket Templates

**Create Template:**
1. Go to **Support** > **Templates**
2. Click **New Template**
3. Enter:
   - Template name
   - Subject
   - Message body
   - Variables ({{customer_name}}, etc.)
4. Save

**Use Template:**
1. In ticket response
2. Click **Templates**
3. Select template
4. Customize if needed
5. Send

---

## Reports & Analytics

### Sales Reports

**Revenue Reports:**
- Daily sales
- Weekly sales
- Monthly sales
- Year-to-date

**Filters:**
- Date range
- Product category
- Customer segment
- Discount code usage

**Export Options:**
- PDF
- CSV
- Excel

### Product Performance

**Metrics:**
- Best sellers
- Worst performers
- Most viewed
- Cart abandonment rate
- Conversion rate

**Inventory Reports:**
- Current stock levels
- Low stock alerts
- Reorder recommendations
- Inventory value

### Customer Analytics

**Customer Insights:**
- New vs returning
- Customer lifetime value
- Average order value
- Purchase frequency
- Geographic distribution

**Cohort Analysis:**
- Retention rates
- Churn analysis
- Engagement metrics

### Marketing Reports

**Campaign Performance:**
- Email open rates
- Click-through rates
- Conversion rates
- ROI by campaign

**Drop Analytics:**
- Waitlist sign-ups
- Conversion rates
- Revenue per drop
- Time to sell out

### Custom Reports

**Create Report:**
1. Go to **Reports** > **Custom**
2. Select metrics
3. Choose dimensions
4. Set filters
5. Generate report
6. Save for future use

---

## System Administration

### User Management

**Admin Users:**
1. Go to **Settings** > **Users**
2. View admin accounts
3. Add new admin
4. Set permissions
5. Deactivate when needed

**Permissions:**
- Full admin
- Products only
- Orders only
- Support only
- Read-only

### System Settings

**General Settings:**
- Site name
- Contact email
- Timezone
- Currency
- Date format

**Email Settings:**
- SMTP configuration
- From address
- Reply-to address
- Test email sending

**Payment Settings:**
- Gateway configuration
- Accepted methods
- Currency settings
- Test mode

**Shipping Settings:**
- Rates and zones
- Free shipping threshold
- Shipping carriers
- International shipping

### Maintenance Mode

**Enable Maintenance:**
1. Go to **Settings** > **Maintenance**
2. Toggle maintenance mode
3. Set message for visitors
4. Whitelist IPs (optional)
5. Save

**Disable:**
1. Return to settings
2. Toggle off
3. Site is live again

### Backup & Restore

**Create Backup:**
1. Go to **Settings** > **Backup**
2. Choose:
   - Database only
   - Files only
   - Full backup
3. Click **Create Backup**
4. Download or store on server

**Restore:**
1. Go to backup section
2. Upload backup file
3. Confirm restoration
4. Wait for completion

### System Health

**Health Check:**
- Database connection
- File permissions
- Disk space
- PHP version
- Server status

**View Logs:**
- Application logs
- Error logs
- Access logs
- System logs

---

## Best Practices

### Security

1. **Change default passwords**
2. **Use strong passwords**
3. **Enable two-factor authentication**
4. **Limit admin access**
5. **Regular security audits**
6. **Keep system updated**

### Order Processing

1. **Process orders promptly**
2. **Verify addresses**
3. **Double-check items**
4. **Update tracking quickly**
5. **Communicate delays**

### Customer Service

1. **Respond within 24 hours**
2. **Be professional and friendly**
3. **Document all interactions**
4. **Escalate when needed**
5. **Follow up on resolutions**

### Content Management

1. **Use high-quality images**
2. **Write clear descriptions**
3. **Keep prices updated**
4. **Review inventory regularly**
5. **Update policies**

---

## Troubleshooting

### Common Issues

**Can't access admin panel:**
- Clear browser cache
- Check credentials
- Verify admin role
- Check server status

**Orders not showing:**
- Refresh page
- Check date filters
- Review permissions
- Check database

**Email not sending:**
- Verify SMTP settings
- Check email logs
- Test email function
- Contact hosting

---

## Getting Help

**Technical Support:**
- Email: tech@mysticclothing.com
- Documentation: `/docs/`
- Issue Tracker: GitHub Issues

**Resources:**
- [Architecture Documentation](../ARCHITECTURE.md)
- [Database Schema](../DATABASE_SCHEMA.md)
- [API Reference](../api/API_REFERENCE.md)
- [Operations Guide](../ops.md)

---

**Last Updated:** November 16, 2024
