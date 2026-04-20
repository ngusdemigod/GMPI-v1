# Church Financial Partnership System

A complete PHP-based church financial partnership platform for Bright Light Ministry Int'l, featuring donor-centric giving, campaign management, and impact tracking.

## Features

### 1. Quick Give Module
- Select giving categories: Tithe, Offering, Missions, Building
- Choose preset amounts ($50, $100, $250, $500, $1,000) or custom input
- Set frequency: One-time, Weekly, Monthly, Annually
- Trust indicators: Secure 256-bit encryption, 501(c)(3) tax-deductible

### 2. Partnership Tracking Dashboard
- Individual donor progress tracking
- Consecutive months of giving counter
- Next milestone targets (Bronze → Silver → Gold → Platinum → Diamond)
- Visual progress toward pledged commitments
- 2024 Faith Pledge tracking with percentage fulfilled

### 3. Active Campaigns Management
- Ongoing initiatives with goal progress
- Current vs. target amounts raised
- Number of active partners per campaign
- Deadline countdowns
- Category tags: Urgent, Missions, Seasonal, Scholarship

### 4. Impact Reporting Section
- Aggregate metrics display
- Social proof: Families fed, Missionaries sent, Campuses planted, Lives touched
- Encourages continued giving through demonstrated impact

## Database Architecture

### Tables
- **users** - User accounts with authentication
- **user_roles** - Role definitions (admin, staff, donor)
- **user_role_assignments** - User-to-role mappings
- **payment_methods** - Stored payment information
- **campaigns** - Active giving campaigns
- **pledges** - User commitment tracking
- **transactions** - Complete donation history
- **campaign_participants** - Campaign contribution tracking
- **tax_receipts** - Tax-deductible receipt records
- **donor_tiers** - Giving level definitions
- **user_donor_tier_history** - Tier progression tracking
- **system_logs** - Error handling and activity logging

## Installation

### Prerequisites
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx)

### Setup Steps

1. **Clone the repository**
   ```bash
   cd church-partnership-php-script
   ```

2. **Create the database**
   ```bash
   mysql -u root -p
   CREATE DATABASE church_partnership;
   EXIT;
   ```

3. **Import the schema**
   ```bash
   mysql -u root -p church_partnership < database/schema.sql
   ```

4. **Configure database connection**
   Edit `src/config/database.php` or set environment variables:
   ```
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=church_partnership
   DB_USER=root
   DB_PASSWORD=your_password
   ```

5. **Start the application**
   ```bash
   # Using PHP built-in server with the public front controller
   php -S localhost:8000 -t public
   
   # Or configure your web server
   ```

6. **Test Paystack webhooks locally with Paystack CLI**
   ```bash
   npm install -g @paystack-oss/dev-cli
   paystack --help
   webhook listen localhost:8000/paystack/webhook
   ```

   Local CLI testing endpoint:
   ```
   http://localhost:8000/paystack/webhook
   ```

   Full app/live-style webhook endpoint:
   ```
   /paystack/webhook/live
   ```

   Set `PAYSTACK_WEBHOOK_URL` in `.env` to your deployed live endpoint and enable:
   - `charge.success`
   - `charge.failed`
   - `subscription.success`
   - `subscription.active`
   - `subscription.failure`
   - `subscription.expired`

   For local listener testing you can also send a sample event:
   ```bash
   webhook ping --event transfer.success --domain test
   ```

## Demo Credentials

**Email:** david.anderson@email.com  
**Password:** password123

## Project Structure

```
church-partnership-php-script/
├── database/
│   └── schema.sql              # Database schema with sample data
├── src/
│   ├── index.php               # Main dashboard
│   ├── login.php               # Login page
│   ├── config/
│   │   └── database.php        # Database connection class
│   └── models/
│       ├── User.php            # User authentication & management
│       ├── Campaign.php        # Campaign management
│       ├── Transaction.php     # Donation processing
│       └── TaxReceipt.php      # Tax receipt generation
├── nginx/
│   └── nginx.conf              # Nginx configuration
├── Dockerfile                  # Docker image definition
├── docker-compose.yml          # Docker services configuration
└── README.md                   # This file
```

## Security Features

- **Password Hashing:** BCrypt with cost factor 12
- **SQL Injection Prevention:** All queries use parameterized statements via PDO
- **Session Management:** Secure PHP sessions with regeneration
- **XSS Protection:** Output escaping with htmlspecialchars()
- **CSRF Protection:** Session-based token validation (can be extended)
- **PCI-DSS Compliant:** Payment processing follows security standards

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `?action=process-donation` | POST | Process a new donation |
| `?action=get-campaigns` | GET | Get active campaigns |
| `?action=get-user-stats` | GET | Get user statistics |

## Tax Receipt Generation

The system automatically generates tax-deductible receipts for all donations. Receipts include:
- Donor information
- Total contributions by year
- Category breakdown
- 501(c)(3) disclaimer
- Unique receipt number

## UI Design

The interface follows a clean gold/dark aesthetic:
- **Primary Colors:** Gold (#D4AF37), Dark Blue (#1a1a2e)
- **Fonts:** Playfair Display (headings), Inter (body)
- **Responsive:** Mobile-friendly design
- **Accessibility:** WCAG 2.1 AA compliant

## Future Enhancements

- Email notification system for receipts
- Recurring donation automation
- Multi-language support
- Admin dashboard for campaign management
- Export reports (CSV, PDF)
- Integration with payment gateways (Stripe, PayPal)
- Mobile app support

## License

© 2024 Bright Light Ministry Int'l. All rights reserved.
